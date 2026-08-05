"""Analisador estático e determinístico para snapshots PHP/Laravel e TypeScript/React.

O módulo só interpreta bytes fornecidos no payload. Não abre paths, não resolve
imports e jamais executa código do repositório analisado.
"""

from __future__ import annotations

import base64
import re
from dataclasses import dataclass
from datetime import UTC, datetime
from hashlib import sha256
from typing import Any

ANALYZER_METHOD = "code.analyzer@1.0.0"
_PHP_CLASS = re.compile(r"(?m)^\s*(?:abstract\s+|final\s+)?class\s+(\w+)([^\n{]*)")
_TS_COMPONENT = re.compile(r"(?m)^\s*(?:export\s+(?:default\s+)?)?(?:function\s+([A-Z]\w*)|const\s+([A-Z]\w*)\s*=)")
_TABLE = re.compile(r"(?:Schema::(?:create|table)|DB::table)\(\s*['\"]([^'\"]+)['\"]")
_MODEL_TABLE = re.compile(r"\$table\s*=\s*['\"]([^'\"]+)['\"]")
_LARAVEL_ROUTE = re.compile(
    r"Route::(get|post|put|patch|delete|any)\(\s*['\"]([^'\"]+)['\"]\s*,\s*\[\s*(\w+)::class",
    re.I,
)


class CodeAnalysisError(ValueError):
    """O snapshot não atende ao contrato seguro do analisador."""


@dataclass(frozen=True)
class CodeAnalysisLimits:
    max_files: int = 5_000
    max_bytes: int = 20 * 1024 * 1024


def analyze_code_task_payload(payload: dict[str, Any], limits: CodeAnalysisLimits | None = None) -> dict[str, object]:
    required = ("revision_id", "source_observation_id", "source_revision", "artifact_uri", "files")
    if any(not isinstance(payload.get(name), str) or not str(payload[name]).strip() for name in required[:-1]):
        raise CodeAnalysisError("revision_id, source_observation_id, source_revision e artifact_uri são obrigatórios")
    files = payload.get("files")
    if not isinstance(files, list):
        raise CodeAnalysisError("files é obrigatório para code.analyze")
    decoded: list[tuple[str, str]] = []
    for item in files:
        if (
            not isinstance(item, dict)
            or not isinstance(item.get("path"), str)
            or not isinstance(item.get("content_base64"), str)
        ):
            raise CodeAnalysisError("cada arquivo exige path e content_base64")
        try:
            decoded.append((item["path"], base64.b64decode(item["content_base64"], validate=True).decode("utf-8")))
        except (ValueError, UnicodeDecodeError) as error:
            raise CodeAnalysisError("content_base64 deve conter UTF-8 Base64 válido") from error
    return analyze_code_snapshot(
        decoded,
        revision_id=str(payload["revision_id"]),
        source_observation_id=str(payload["source_observation_id"]),
        source_revision=str(payload["source_revision"]),
        artifact_uri=str(payload["artifact_uri"]),
        limits=limits,
    )


def analyze_code_snapshot(
    files: list[tuple[str, str]],
    *,
    revision_id: str,
    source_observation_id: str,
    source_revision: str,
    artifact_uri: str,
    limits: CodeAnalysisLimits | None = None,
) -> dict[str, object]:
    active_limits = limits or CodeAnalysisLimits()
    if len(files) > active_limits.max_files or sum(len(text.encode()) for _, text in files) > active_limits.max_bytes:
        raise CodeAnalysisError("snapshot excede os limites do analisador")
    provenance = {
        "source_observation_id": source_observation_id,
        "source_revision": source_revision,
        "method": ANALYZER_METHOD,
        "processed_at": datetime.now(UTC).isoformat().replace("+00:00", "Z"),
    }
    candidates: list[dict[str, object]] = []
    assertions: list[dict[str, object]] = []
    for path, text in files:
        if path.endswith(".php"):
            php_candidates, php_assertions = _analyze_php(path, text, provenance)
            candidates.extend(php_candidates)
            assertions.extend(php_assertions)
        elif path.endswith((".tsx", ".jsx", ".ts", ".js")):
            ts_candidates, ts_assertions = _analyze_react(path, text, provenance)
            candidates.extend(ts_candidates)
            assertions.extend(ts_assertions)
    route_candidates, route_assertions = _analyze_laravel_routes(files, candidates, provenance)
    candidates.extend(route_candidates)
    assertions.extend(route_assertions)
    assertions.extend(_cross_file_php_assertions(files, candidates, provenance))
    return {
        "schema_version": "1.0.0",
        "event_type": "CodeAnalyzed",
        "revision_id": revision_id,
        "artifact_uri": artifact_uri,
        "provenance": provenance,
        "execution": {"analyzer_version": ANALYZER_METHOD, "kind": "static_analysis", "reprocessable": True},
        "candidates": candidates,
        "assertions": assertions,
    }


def _analyze_laravel_routes(
    files: list[tuple[str, str]], candidates: list[dict[str, object]], provenance: dict[str, str]
) -> tuple[list[dict[str, object]], list[dict[str, object]]]:
    """Extrai rotas declarativas sem carregar o framework ou arquivos externos."""
    controllers = {
        str(candidate["title"]): str(candidate["stable_key"])
        for candidate in candidates
        if candidate["entity_type"] == "Controller"
    }
    route_candidates: list[dict[str, object]] = []
    assertions: list[dict[str, object]] = []
    for path, text in files:
        if not path.endswith(".php") or "/routes/" not in f"/{path}":
            continue
        for match in _LARAVEL_ROUTE.finditer(text):
            method = match.group(1).upper()
            route_path = match.group(2)
            controller = match.group(3)
            endpoint = f"{method} {route_path}"
            endpoint_key = _key("Endpoint", endpoint)
            route_candidates.append(
                _candidate("Endpoint", endpoint, endpoint_key, path, text, match.start(), provenance)
            )
            controller_key = controllers.get(controller)
            if controller_key is not None:
                assertions.append(
                    _assertion(controller_key, "exposes", endpoint_key, path, text, match.start(), provenance)
                )
    return _dedupe_candidates(route_candidates), _dedupe_assertions(assertions)


def _cross_file_php_assertions(
    files: list[tuple[str, str]], candidates: list[dict[str, object]], provenance: dict[str, str]
) -> list[dict[str, object]]:
    """Resolve referências nominais entre classes do mesmo snapshot, sem I/O."""
    known = {
        str(item["title"]): str(item["stable_key"])
        for item in candidates
        if item["entity_type"] in {"Controller", "Service", "Job", "Evento", "Listener", "Model", "Migration"}
    }
    assertions: list[dict[str, object]] = []
    for path, text in files:
        if not path.endswith(".php"):
            continue
        for source in _PHP_CLASS.finditer(text):
            source_name = source.group(1)
            source_key = known.get(source_name)
            if source_key is None:
                continue
            body = text[source.end() :]
            for target_name, target_key in known.items():
                if target_name == source_name:
                    continue
                offset = text.find(target_name, source.end())
                if re.search(
                    rf"(?:\b{re.escape(target_name)}::(?:dispatch|dispatchSync)\s*\(|event\s*\(\s*new\s+{re.escape(target_name)}\b)",
                    body,
                ):
                    assertions.append(_assertion(source_key, "produces", target_key, path, text, offset, provenance))
                elif re.search(rf"(?:new\s+{re.escape(target_name)}\b|\b{re.escape(target_name)}::\w+\s*\()", body):
                    assertions.append(_assertion(source_key, "calls", target_key, path, text, offset, provenance))
            if source_key.startswith("code:listener:"):
                handled = re.search(r"function\s+handle\s*\(\s*(\w+)", body)
                if handled is not None and handled.group(1) in known:
                    assertions.append(
                        _assertion(
                            source_key,
                            "consumes",
                            known[handled.group(1)],
                            path,
                            text,
                            source.end() + handled.start(1),
                            provenance,
                        )
                    )
    return _dedupe_assertions(assertions)


def _analyze_php(
    path: str, text: str, provenance: dict[str, str]
) -> tuple[list[dict[str, object]], list[dict[str, object]]]:
    candidates: list[dict[str, object]] = []
    assertions: list[dict[str, object]] = []
    classes: dict[str, str] = {}
    for match in _PHP_CLASS.finditer(text):
        name, suffix = match.group(1), match.group(2)
        kind = _php_kind(path, name, suffix)
        if kind is None:
            continue
        key = _key(kind, name)
        classes[name] = key
        candidates.append(_candidate(kind, name, key, path, text, match.start(), provenance))
    for match in _TABLE.finditer(text):
        table = match.group(1)
        key = _key("Tabela", table)
        candidates.append(_candidate("Tabela", table, key, path, text, match.start(1), provenance))
    for match in _MODEL_TABLE.finditer(text):
        table = match.group(1)
        key = _key("Tabela", table)
        candidates.append(_candidate("Tabela", table, key, path, text, match.start(1), provenance))
    if ("migration" in path.lower()) and not any(item["entity_type"] == "Migration" for item in candidates):
        migration_name = path.rsplit("/", 1)[-1].rsplit(".", 1)[0]
        migration_key = _key("Migration", migration_name)
        classes[migration_name] = migration_key
        candidates.append(_candidate("Migration", migration_name, migration_key, path, text, 0, provenance))
        for table in _TABLE.finditer(text):
            assertions.append(
                _assertion(
                    migration_key, "writes", _key("Tabela", table.group(1)), path, text, table.start(), provenance
                )
            )
    for source_name, source_key in classes.items():
        class_match = next((m for m in _PHP_CLASS.finditer(text) if m.group(1) == source_name), None)
        if class_match is None:
            continue
        body = text[class_match.end() :]
        for target_name, target_key in classes.items():
            if target_name == source_name:
                continue
            target_offset = text.find(target_name, class_match.end())
            if re.search(rf"\b{re.escape(target_name)}::(?:dispatch|dispatchSync)\s*\(", body) or re.search(
                rf"event\s*\(\s*new\s+{re.escape(target_name)}\b", body
            ):
                assertions.append(_assertion(source_key, "produces", target_key, path, text, target_offset, provenance))
            elif re.search(rf"(?:new\s+{re.escape(target_name)}\b|\b{re.escape(target_name)}::\w+\s*\()", body):
                assertions.append(_assertion(source_key, "calls", target_key, path, text, target_offset, provenance))
        for table in _TABLE.finditer(body):
            operation = body[table.end() : table.end() + 160]
            predicate = "writes" if re.search(r"->(?:insert|update|delete|create|upsert)\s*\(", operation) else "reads"
            absolute = class_match.end() + table.start()
            assertions.append(
                _assertion(source_key, predicate, _key("Tabela", table.group(1)), path, text, absolute, provenance)
            )
        if source_key.startswith("code:model:"):
            for table in _MODEL_TABLE.finditer(body):
                absolute = class_match.end() + table.start()
                assertions.append(
                    _assertion(source_key, "writes", _key("Tabela", table.group(1)), path, text, absolute, provenance)
                )
        if source_key.startswith("code:listener:"):
            handled = re.search(r"function\s+handle\s*\(\s*(\w+)", body)
            if handled is not None and handled.group(1) in classes:
                assertions.append(
                    _assertion(
                        source_key,
                        "consumes",
                        classes[handled.group(1)],
                        path,
                        text,
                        class_match.end() + handled.start(1),
                        provenance,
                    )
                )
        if source_key.startswith("code:migration:"):
            for table in _TABLE.finditer(text):
                assertions.append(
                    _assertion(
                        source_key, "writes", _key("Tabela", table.group(1)), path, text, table.start(), provenance
                    )
                )
    return _dedupe_candidates(candidates), _dedupe_assertions(assertions)


def _php_kind(path: str, name: str, suffix: str) -> str | None:
    lower = path.lower()
    if "migration" in lower or "extends migration" in suffix.lower():
        return "Migration"
    if "controller" in lower or name.endswith("Controller"):
        return "Controller"
    if "service" in lower or name.endswith("Service"):
        return "Service"
    if "jobs/" in lower or name.endswith("Job") or "shouldqueue" in suffix.lower():
        return "Job"
    if "events/" in lower or name.endswith("Event"):
        return "Evento"
    if "listeners/" in lower or name.endswith("Listener"):
        return "Listener"
    if "models/" in lower or "extends model" in suffix.lower():
        return "Model"
    return None


def _analyze_react(
    path: str, text: str, provenance: dict[str, str]
) -> tuple[list[dict[str, object]], list[dict[str, object]]]:
    candidates: list[dict[str, object]] = []
    assertions: list[dict[str, object]] = []
    for match in _TS_COMPONENT.finditer(text):
        name = match.group(1) or match.group(2)
        if name is None:
            continue
        key = _key("Tela", name)
        candidates.append(_candidate("Tela", name, key, path, text, match.start(), provenance))
        for request in re.finditer(r"\b(fetch|axios\.(get|post|put|patch|delete))\s*\(\s*['\"]([^'\"]+)", text):
            method = request.group(2) or "GET"
            if request.group(1) == "fetch":
                method_match = re.search(
                    r"\bmethod\s*:\s*['\"](GET|POST|PUT|PATCH|DELETE)['\"]",
                    text[request.end() : request.end() + 200],
                    re.I,
                )
                if method_match is not None:
                    method = method_match.group(1)
            method = method.upper()
            target = f"http:{method}:{request.group(3)}"
            assertions.append(_assertion(key, "calls", target, path, text, request.start(), provenance, external=True))
    return _dedupe_candidates(candidates), _dedupe_assertions(assertions)


def _key(kind: str, name: str) -> str:
    return f"code:{kind.lower()}:{name}"


def _candidate(
    kind: str, title: str, key: str, path: str, text: str, offset: int, provenance: dict[str, str]
) -> dict[str, object]:
    return {
        "entity_type": kind,
        "title": title,
        "stable_key": key,
        "lifecycle_state": "candidate",
        "confidence": 0.92,
        "provenance": provenance,
        "evidence": _evidence(path, text, offset, provenance),
    }


def _assertion(
    subject: str,
    predicate: str,
    target: str,
    path: str,
    text: str,
    offset: int,
    provenance: dict[str, str],
    external: bool = False,
) -> dict[str, object]:
    result: dict[str, object] = {
        "subject_stable_key": subject,
        "predicate": predicate,
        "object_stable_key": target,
        "lifecycle_state": "candidate",
        "confidence": 0.9,
        "method": ANALYZER_METHOD,
        "provenance": provenance,
        "evidence": _evidence(path, text, max(offset, 0), provenance),
    }
    if external:
        result["object_resolution"] = "external_reference"
    return result


def _evidence(path: str, text: str, offset: int, provenance: dict[str, str]) -> dict[str, object]:
    line = text.count("\n", 0, offset) + 1
    excerpt = text.splitlines()[line - 1] if text.splitlines() else ""
    return {
        "kind": "artifact",
        "pointer": f"{path}:{line}",
        "offsets": {"start": line, "end": line, "unit": "line"},
        "checksum": sha256(excerpt.encode()).hexdigest(),
        "commit": provenance["source_revision"],
        "provenance": provenance,
    }


def _dedupe_candidates(items: list[dict[str, object]]) -> list[dict[str, object]]:
    return list({str(item["stable_key"]): item for item in items}.values())


def _dedupe_assertions(items: list[dict[str, object]]) -> list[dict[str, object]]:
    return list(
        {
            (
                str(item["subject_stable_key"]),
                str(item["predicate"]),
                str(item["object_stable_key"]),
                str(item["evidence"]),
            ): item
            for item in items
        }.values()
    )
