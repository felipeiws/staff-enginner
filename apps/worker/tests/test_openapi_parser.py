from __future__ import annotations

import base64
from pathlib import Path

import pytest

from atlas_worker.openapi_parser import OpenApiValidationError, parse_openapi_document, parse_openapi_task_payload

ROOT = Path(__file__).parents[3]
REVISION_ID = "00000000-0000-4000-8000-000000000101"
OBSERVATION_ID = "00000000-0000-4000-8000-000000000102"


def parse_fixture(name: str, previous: str | None = None) -> dict[str, object]:
    return parse_openapi_document(
        (ROOT / "apps/worker/tests/fixtures" / name).read_bytes(),
        revision_id=REVISION_ID,
        source_observation_id=OBSERVATION_ID,
        source_revision="git:openapi-fixture",
        artifact_uri="openapi/compras.yaml",
        previous_content=(ROOT / "apps/worker/tests/fixtures" / previous).read_bytes() if previous else None,
    )


def test_extracts_endpoint_fields_and_candidate_assertions_from_openapi_3() -> None:
    result = parse_fixture("openapi-valid.yaml")
    endpoint = next(item for item in result["candidates"] if item["entity_type"] == "Endpoint")  # type: ignore[index]
    attributes = endpoint["attributes"]  # type: ignore[index]
    assert attributes["method"] == "DELETE" and attributes["path"] == "/purchase-orders/{id}"
    assert attributes["parameters"] == [
        {
            "name": "id",
            "location": "path",
            "type": "string",
            "required": True,
            "description": "Identificador do pedido",
        },
        {
            "name": "dry_run",
            "location": "query",
            "type": "boolean",
            "required": False,
            "description": "Apenas valida o cancelamento.",
        },
    ]
    assert attributes["request_bodies"][0]["schema"] == "#/components/schemas/Cancelamento"
    response = next(item for item in attributes["responses"] if item["code"] == "409")
    assert response["examples"][0]["example"]["rule"] == "RN-142"
    predicates = {item["predicate"] for item in result["assertions"]}  # type: ignore[index]
    assert {"exposes", "consumes", "produces"} <= predicates
    assert all(item["lifecycle_state"] == "candidate" for item in result["candidates"])  # type: ignore[index]
    assert any(item["entity_type"] == "Evento" for item in result["candidates"])  # type: ignore[index]


def test_rejects_an_invalid_openapi_version() -> None:
    with pytest.raises(OpenApiValidationError, match="3.x"):
        parse_fixture("openapi-invalid.yaml")


def test_emits_breaking_contract_divergence_between_revisions() -> None:
    result = parse_fixture("openapi-breaking.yaml", "openapi-valid.yaml")
    kinds = {item["kind"] for item in result["contract_divergences"]}  # type: ignore[index]
    assert {"required_parameter_added", "success_response_removed"} <= kinds
    assert all(item["severity"] == "breaking" for item in result["contract_divergences"])  # type: ignore[index]


def test_task_payload_preserves_the_revision_and_detects_a_previous_contract() -> None:
    payload = {
        "revision_id": REVISION_ID,
        "source_observation_id": OBSERVATION_ID,
        "source_revision": "git:openapi-new",
        "artifact_uri": "openapi/compras.yaml",
        "content_base64": base64.b64encode(
            (ROOT / "apps/worker/tests/fixtures/openapi-breaking.yaml").read_bytes()
        ).decode(),
        "previous_content_base64": base64.b64encode(
            (ROOT / "apps/worker/tests/fixtures/openapi-valid.yaml").read_bytes()
        ).decode(),
    }
    result = parse_openapi_task_payload(payload)
    assert result["revision_id"] == REVISION_ID
    assert result["contract_divergences"]  # type: ignore[truthy-bool]
