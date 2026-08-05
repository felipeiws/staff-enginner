"""Parser determinístico de OpenAPI 3.x para candidatos do Knowledge Engine.

O parser recebe somente bytes já obtidos por um conector. Referências externas
não são seguidas: cada campo produzido aponta para a revisão de origem e fica
no estado ``candidate`` para a política e a revisão humana decidirem.
"""

from __future__ import annotations

import base64
import json
from dataclasses import dataclass
from datetime import UTC, datetime
from hashlib import sha256
from typing import Any

import yaml  # type: ignore[import-untyped]

PARSER_METHOD = "openapi.parser@1.0.0"
HTTP_METHODS = frozenset({"get", "put", "post", "delete", "options", "head", "patch", "trace"})


class OpenApiValidationError(ValueError):
    """A especificação não é uma OpenAPI 3.x utilizável pelo parser."""


@dataclass(frozen=True)
class OpenApiParseLimits:
    max_bytes: int = 10 * 1024 * 1024


def parse_openapi_task_payload(payload: dict[str, Any], limits: OpenApiParseLimits | None = None) -> dict[str, object]:
    encoded = payload.get("content_base64")
    if not isinstance(encoded, str):
        raise OpenApiValidationError("content_base64 é obrigatório para openapi.parse")
    try:
        content = base64.b64decode(encoded, validate=True)
    except ValueError as error:
        raise OpenApiValidationError("content_base64 deve ser Base64 válido") from error
    required = ("revision_id", "source_observation_id", "source_revision", "artifact_uri")
    if any(not isinstance(payload.get(name), str) or not str(payload[name]).strip() for name in required):
        raise OpenApiValidationError(
            "revision_id, source_observation_id, source_revision e artifact_uri são obrigatórios"
        )
    previous = _decode_optional(payload.get("previous_content_base64"), "previous_content_base64")
    return parse_openapi_document(
        content,
        revision_id=str(payload["revision_id"]),
        source_observation_id=str(payload["source_observation_id"]),
        source_revision=str(payload["source_revision"]),
        artifact_uri=str(payload["artifact_uri"]),
        previous_content=previous,
        limits=limits,
    )


def parse_openapi_document(
    content: bytes,
    *,
    revision_id: str,
    source_observation_id: str,
    source_revision: str,
    artifact_uri: str,
    previous_content: bytes | None = None,
    limits: OpenApiParseLimits | None = None,
) -> dict[str, object]:
    active_limits = limits or OpenApiParseLimits()
    if len(content) > active_limits.max_bytes:
        raise OpenApiValidationError("especificação excede o limite de tamanho do parser")
    document = _load_document(content)
    _validate_document(document)
    previous = _load_document(previous_content) if previous_content is not None else None
    if previous is not None:
        _validate_document(previous)

    processed_at = datetime.now(UTC).isoformat().replace("+00:00", "Z")
    provenance = {
        "source_observation_id": source_observation_id,
        "source_revision": source_revision,
        "method": PARSER_METHOD,
        "processed_at": processed_at,
    }
    application = _application_candidate(document, artifact_uri, provenance)
    schemas = _schema_candidates(document, artifact_uri, provenance)
    events = _event_candidates(document, artifact_uri, provenance)
    endpoints, assertions = _endpoint_candidates(document, application, schemas, events, artifact_uri, provenance)
    candidates = [application, *schemas, *events, *endpoints]
    return {
        "schema_version": "1.0.0",
        "event_type": "ContentParsed",
        "revision_id": revision_id,
        "artifact_uri": artifact_uri,
        "provenance": provenance,
        "chunks": [_artifact_chunk(content, artifact_uri, provenance)],
        "candidates": candidates,
        "assertions": assertions,
        "contract_divergences": [] if previous is None else _breaking_changes(previous, document),
    }


def _decode_optional(value: object, field: str) -> bytes | None:
    if value is None:
        return None
    if not isinstance(value, str):
        raise OpenApiValidationError(f"{field} deve ser Base64 válido")
    try:
        return base64.b64decode(value, validate=True)
    except ValueError as error:
        raise OpenApiValidationError(f"{field} deve ser Base64 válido") from error


def _load_document(content: bytes | None) -> dict[str, Any]:
    if content is None:
        raise OpenApiValidationError("conteúdo OpenAPI ausente")
    try:
        value = json.loads(content) if content.lstrip().startswith((b"{", b"[")) else yaml.safe_load(content)
    except (json.JSONDecodeError, UnicodeDecodeError, yaml.YAMLError) as error:
        raise OpenApiValidationError("especificação OpenAPI JSON ou YAML inválida") from error
    if not isinstance(value, dict):
        raise OpenApiValidationError("a raiz da especificação OpenAPI deve ser um objeto")
    return value


def _validate_document(document: dict[str, Any]) -> None:
    version = document.get("openapi")
    if not isinstance(version, str) or not version.startswith("3."):
        raise OpenApiValidationError("a especificação deve declarar openapi 3.x")
    if not isinstance(document.get("info"), dict) or not isinstance(document["info"].get("title"), str):
        raise OpenApiValidationError("info.title é obrigatório")
    if not isinstance(document.get("paths"), dict):
        raise OpenApiValidationError("paths deve ser um objeto")


def _artifact_chunk(content: bytes, artifact_uri: str, provenance: dict[str, str]) -> dict[str, object]:
    text = content.decode("utf-8", errors="replace")
    return {
        "ordinal": 0,
        "start_offset": 0,
        "end_offset": len(text),
        "text": text,
        "tokens": len(text.split()),
        "checksum": sha256(content).hexdigest(),
        "artifact_uri": artifact_uri,
        "provenance": provenance,
    }


def _candidate(
    entity_type: str,
    title: str,
    stable_key: str,
    attributes: dict[str, Any],
    artifact_uri: str,
    provenance: dict[str, str],
) -> dict[str, object]:
    return {
        "entity_type": entity_type,
        "title": title,
        "stable_key": stable_key,
        "lifecycle_state": "candidate",
        "confidence": 1.0,
        "attributes": attributes,
        "provenance": provenance,
        "evidence": {
            "kind": "artifact",
            "artifact_uri": artifact_uri,
            "checksum": sha256(artifact_uri.encode()).hexdigest(),
        },
    }


def _application_candidate(
    document: dict[str, Any], artifact_uri: str, provenance: dict[str, str]
) -> dict[str, object]:
    info = _mapping(document.get("info"))
    title = _text(info.get("title"))
    return _candidate(
        "Aplicação",
        title,
        _key("application", title),
        {"description": _text(info.get("description")), "version": _text(info.get("version"))},
        artifact_uri,
        provenance,
    )


def _schema_candidates(
    document: dict[str, Any], artifact_uri: str, provenance: dict[str, str]
) -> list[dict[str, object]]:
    schemas = _mapping(_mapping(document.get("components")).get("schemas"))
    return [
        _candidate("Schema", name, _key("schema", name), _schema_view(schema, document), artifact_uri, provenance)
        for name, schema in schemas.items()
        if isinstance(name, str) and isinstance(schema, dict)
    ]


def _event_candidates(
    document: dict[str, Any], artifact_uri: str, provenance: dict[str, str]
) -> list[dict[str, object]]:
    raw_events = document.get("x-atlas-events", document.get("x-events", []))
    values = raw_events.values() if isinstance(raw_events, dict) else raw_events if isinstance(raw_events, list) else []
    names: set[str] = set()
    for item in values:
        name = _event_name(item)
        if name is not None:
            names.add(name)
    for operation in _operations(document).values():
        for item in _operation_events(operation):
            name = _event_name(item)
            if name is not None:
                names.add(name)
    return [_candidate("Evento", name, _key("event", name), {}, artifact_uri, provenance) for name in sorted(names)]


def _endpoint_candidates(
    document: dict[str, Any],
    application: dict[str, object],
    schemas: list[dict[str, object]],
    events: list[dict[str, object]],
    artifact_uri: str,
    provenance: dict[str, str],
) -> tuple[list[dict[str, object]], list[dict[str, object]]]:
    schema_keys = {
        name: _key("schema", name)
        for name in _mapping(_mapping(document.get("components")).get("schemas"))
        if isinstance(name, str)
    }
    raw_events = document.get("x-atlas-events", document.get("x-events", []))
    event_values = (
        raw_events.values() if isinstance(raw_events, dict) else raw_events if isinstance(raw_events, list) else []
    )
    event_keys = {name: _key("event", name) for item in event_values if (name := _event_name(item)) is not None}
    endpoints: list[dict[str, object]] = []
    assertions: list[dict[str, object]] = []
    for path, path_item in _mapping(document.get("paths")).items():
        if not isinstance(path, str) or not isinstance(path_item, dict):
            continue
        shared = _parameters(path_item.get("parameters"), document)
        for method, operation in path_item.items():
            if method.lower() not in HTTP_METHODS or not isinstance(operation, dict):
                continue
            normalized_method = method.upper()
            endpoint_key = _key("endpoint", f"{normalized_method} {path}")
            parameters = _deduplicate_parameters([*shared, *_parameters(operation.get("parameters"), document)])
            request_bodies = _request_bodies(operation.get("requestBody"), document)
            responses = _responses(operation.get("responses"), document)
            servers = (
                _servers(operation.get("servers"))
                or _servers(path_item.get("servers"))
                or _servers(document.get("servers"))
            )
            attributes = {
                "method": normalized_method,
                "path": path,
                "description": _text(operation.get("description")) or _text(operation.get("summary")),
                "operation_id": _text(operation.get("operationId")),
                "parameters": parameters,
                "request_bodies": request_bodies,
                "responses": responses,
                "servers": servers,
            }
            endpoints.append(
                _candidate(
                    "Endpoint", f"{normalized_method} {path}", endpoint_key, attributes, artifact_uri, provenance
                )
            )
            assertions.append(_assertion(str(application["stable_key"]), "exposes", endpoint_key, provenance))
            for body in request_bodies:
                _schema_assertion(assertions, endpoint_key, "consumes", body.get("schema"), schema_keys, provenance)
            for response in responses:
                _schema_assertion(assertions, endpoint_key, "produces", response.get("schema"), schema_keys, provenance)
            for event in _operation_events(operation):
                name = _event_name(event)
                if name is not None:
                    event_keys.setdefault(name, _key("event", name))
                    assertions.append(_assertion(endpoint_key, _event_direction(event), event_keys[name], provenance))
    return endpoints, assertions


def _parameters(value: object, document: dict[str, Any]) -> list[dict[str, object]]:
    if not isinstance(value, list):
        return []
    result = []
    for raw in value:
        parameter = _resolve(raw, document)
        if not isinstance(parameter, dict):
            continue
        schema = _resolve(parameter.get("schema"), document)
        result.append(
            {
                "name": _text(parameter.get("name")),
                "location": _text(parameter.get("in")),
                "type": _schema_type(schema),
                "required": bool(parameter.get("required", False)) or parameter.get("in") == "path",
                "description": _text(parameter.get("description")),
            }
        )
    return result


def _deduplicate_parameters(parameters: list[dict[str, object]]) -> list[dict[str, object]]:
    unique: dict[tuple[str, str], dict[str, object]] = {}
    for parameter in parameters:
        unique[(str(parameter["name"]), str(parameter["location"]))] = parameter
    return list(unique.values())


def _request_bodies(value: object, document: dict[str, Any]) -> list[dict[str, object]]:
    body = _resolve(value, document)
    if not isinstance(body, dict):
        return []
    return [
        {
            "media_type": media_type,
            "required": bool(body.get("required", False)),
            "description": _text(body.get("description")),
            "schema": _schema_reference(content.get("schema")),
            "example": _example(content),
        }
        for media_type, content in _mapping(body.get("content")).items()
        if isinstance(media_type, str) and isinstance(content, dict)
    ]


def _responses(value: object, document: dict[str, Any]) -> list[dict[str, object]]:
    responses = _mapping(value)
    result = []
    for code, raw in responses.items():
        response = _resolve(raw, document)
        if not isinstance(code, str) or not isinstance(response, dict):
            continue
        content = _mapping(response.get("content"))
        variants = [
            {"media_type": media_type, "schema": _schema_reference(media.get("schema")), "example": _example(media)}
            for media_type, media in content.items()
            if isinstance(media_type, str) and isinstance(media, dict)
        ]
        result.append(
            {
                "code": code,
                "description": _text(response.get("description")),
                "examples": variants,
                "schema": next((item["schema"] for item in variants if item["schema"]), None),
            }
        )
    return result


def _schema_view(schema: dict[str, Any], document: dict[str, Any]) -> dict[str, object]:
    resolved = _resolve(schema, document)
    return {
        "type": _schema_type(resolved),
        "description": _text(resolved.get("description")) if isinstance(resolved, dict) else "",
        "required": list(_mapping(resolved).get("required", [])),
    }


def _servers(value: object) -> list[dict[str, str]]:
    if not isinstance(value, list):
        return []
    return [
        {"url": _text(item.get("url")), "description": _text(item.get("description"))}
        for item in value
        if isinstance(item, dict) and _text(item.get("url"))
    ]


def _operation_events(operation: dict[str, Any]) -> list[object]:
    value = operation.get("x-atlas-events", operation.get("x-events", []))
    return list(value.values()) if isinstance(value, dict) else value if isinstance(value, list) else []


def _event_name(value: object) -> str | None:
    if isinstance(value, str) and value.strip():
        return value.strip()
    if isinstance(value, dict):
        name = value.get("name", value.get("event"))
        return name.strip() if isinstance(name, str) and name.strip() else None
    return None


def _event_direction(value: object) -> str:
    return (
        "consumes"
        if isinstance(value, dict) and str(value.get("direction", "produces")).lower() == "consumes"
        else "produces"
    )


def _schema_assertion(
    assertions: list[dict[str, object]],
    endpoint: str,
    predicate: str,
    reference: object,
    schema_keys: dict[str, str],
    provenance: dict[str, str],
) -> None:
    if isinstance(reference, str):
        name = reference.rsplit("/", 1)[-1]
        assertions.append(_assertion(endpoint, predicate, schema_keys.get(name, _key("schema", name)), provenance))


def _assertion(subject: str, predicate: str, object_key: str, provenance: dict[str, str]) -> dict[str, object]:
    return {
        "subject_stable_key": subject,
        "predicate": predicate,
        "object_stable_key": object_key,
        "lifecycle_state": "candidate",
        "confidence": 1.0,
        "method": PARSER_METHOD,
        "provenance": provenance,
    }


def _resolve(value: object, document: dict[str, Any]) -> object:
    if not isinstance(value, dict) or not isinstance(value.get("$ref"), str):
        return value
    reference = value["$ref"]
    if not reference.startswith("#/"):
        raise OpenApiValidationError("referências OpenAPI externas não são permitidas")
    current: object = document
    for token in reference[2:].split("/"):
        if not isinstance(current, dict) or token.replace("~1", "/").replace("~0", "~") not in current:
            raise OpenApiValidationError(f"referência OpenAPI interna não encontrada: {reference}")
        current = current[token.replace("~1", "/").replace("~0", "~")]
    return current


def _schema_reference(value: object) -> str | None:
    return value.get("$ref") if isinstance(value, dict) and isinstance(value.get("$ref"), str) else None


def _schema_type(value: object) -> str:
    return _text(value.get("type")) if isinstance(value, dict) and _text(value.get("type")) else "object"


def _example(content: dict[str, Any]) -> object:
    if "example" in content:
        return content["example"]
    examples = _mapping(content.get("examples"))
    first = next(iter(examples.values()), None)
    return first.get("value") if isinstance(first, dict) else None


def _breaking_changes(previous: dict[str, Any], current: dict[str, Any]) -> list[dict[str, object]]:
    divergences: list[dict[str, object]] = []
    old_operations = _operations(previous)
    new_operations = _operations(current)
    for key, old in old_operations.items():
        new = new_operations.get(key)
        if new is None:
            divergences.append(_divergence("endpoint_removed", key, "Endpoint removido da especificação."))
            continue
        old_params, new_params = _parameter_index(old, previous), _parameter_index(new, current)
        for parameter, old_value in old_params.items():
            if parameter not in new_params:
                divergences.append(
                    _divergence("parameter_removed", key, f"Parâmetro removido: {parameter[1]} ({parameter[0]}).")
                )
            elif not bool(old_value["required"]) and bool(new_params[parameter]["required"]):
                divergences.append(
                    _divergence(
                        "parameter_became_required",
                        key,
                        f"Parâmetro passou a ser obrigatório: {parameter[1]} ({parameter[0]}).",
                    )
                )
        for parameter, new_value in new_params.items():
            if parameter not in old_params and bool(new_value["required"]):
                divergences.append(
                    _divergence(
                        "required_parameter_added", key, f"Novo parâmetro obrigatório: {parameter[1]} ({parameter[0]})."
                    )
                )
        old_success = {
            str(item["code"])
            for item in _responses(old.get("responses"), previous)
            if str(item["code"]).startswith("2")
        }
        new_success = {
            str(item["code"]) for item in _responses(new.get("responses"), current) if str(item["code"]).startswith("2")
        }
        for response in old_success - new_success:
            divergences.append(
                _divergence("success_response_removed", key, f"Resposta de sucesso removida: {response}")
            )
    return divergences


def _operations(document: dict[str, Any]) -> dict[str, dict[str, Any]]:
    result: dict[str, dict[str, Any]] = {}
    for path, item in _mapping(document.get("paths")).items():
        if not isinstance(path, str) or not isinstance(item, dict):
            continue
        for method, operation in item.items():
            if method.lower() in HTTP_METHODS and isinstance(operation, dict):
                result[f"{method.upper()} {path}"] = operation
    return result


def _parameter_index(operation: dict[str, Any], document: dict[str, Any]) -> dict[tuple[str, str], dict[str, object]]:
    return {
        (str(item["location"]), str(item["name"])): item for item in _parameters(operation.get("parameters"), document)
    }


def _divergence(kind: str, endpoint: str, message: str) -> dict[str, object]:
    return {"kind": kind, "severity": "breaking", "endpoint": endpoint, "message": message, "method": PARSER_METHOD}


def _mapping(value: object) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


def _text(value: object) -> str:
    return value.strip() if isinstance(value, str) else ""


def _key(kind: str, value: str) -> str:
    return f"{kind}:{sha256(value.encode()).hexdigest()}"
