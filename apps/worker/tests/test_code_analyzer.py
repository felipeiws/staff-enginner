from __future__ import annotations

import base64
from pathlib import Path

import pytest

from atlas_worker.code_analyzer import CodeAnalysisError, analyze_code_snapshot, analyze_code_task_payload

ROOT = Path(__file__).parents[3]
FIXTURES = ROOT / "apps/worker/tests/fixtures/code"


def _files(stack: str) -> list[tuple[str, str]]:
    directory = FIXTURES / stack
    return [(str(path.relative_to(directory)), path.read_text()) for path in directory.rglob("*") if path.is_file()]


def _analyze(stack: str) -> dict[str, object]:
    return analyze_code_snapshot(
        _files(stack),
        revision_id="00000000-0000-4000-8000-000000000101",
        source_observation_id="00000000-0000-4000-8000-000000000102",
        source_revision="a91f3ce",
        artifact_uri="git://acme/checkout",
    )


def test_laravel_static_analysis_extracts_candidates_relations_and_line_commit_evidence() -> None:
    result = _analyze("laravel")
    types = {candidate["entity_type"] for candidate in result["candidates"]}  # type: ignore[index]
    assert {"Controller", "Service", "Job", "Evento", "Tabela", "Migration"} <= types
    assertions = result["assertions"]  # type: ignore[index]
    predicates = {assertion["predicate"] for assertion in assertions}
    assert {"calls", "writes", "produces"} <= predicates
    evidence = next(assertion["evidence"] for assertion in assertions if assertion["predicate"] == "writes")
    assert evidence["pointer"].endswith("PurchaseOrderCancelService.php:4")
    assert evidence["commit"] == "a91f3ce"
    assert result["execution"] == {
        "analyzer_version": "code.analyzer@1.0.0",
        "kind": "static_analysis",
        "reprocessable": True,
    }


def test_laravel_route_listener_and_model_are_static_candidates_with_line_evidence() -> None:
    files = _files("laravel") + [
        (
            "routes/web.php",
            "<?php\nRoute::post('/purchase-orders/{order}/cancel', [PurchaseOrderController::class, 'cancel']);\n",
        ),
        (
            "app/Listeners/ReleaseStockListener.php",
            "<?php\nclass ReleaseStockListener { public function handle(PurchaseOrderCancelled $event): void {} }\n",
        ),
        (
            "app/Models/PurchaseOrder.php",
            "<?php\nclass PurchaseOrder extends Model { protected $table = 'purchase_orders'; }\n",
        ),
    ]
    result = analyze_code_snapshot(
        files,
        revision_id="00000000-0000-4000-8000-000000000101",
        source_observation_id="00000000-0000-4000-8000-000000000102",
        source_revision="a91f3ce",
        artifact_uri="git://acme/checkout",
    )
    types = {candidate["entity_type"] for candidate in result["candidates"]}  # type: ignore[index]
    assert {"Endpoint", "Listener", "Model"} <= types
    assertions = result["assertions"]  # type: ignore[index]
    exposed = next(item for item in assertions if item["predicate"] == "exposes")
    assert exposed["evidence"]["pointer"] == "routes/web.php:2"
    consumed = next(item for item in assertions if item["predicate"] == "consumes")
    assert consumed["object_stable_key"] == "code:evento:PurchaseOrderCancelled"


def test_react_static_analysis_emits_screen_and_http_call_without_executing_source() -> None:
    result = _analyze("react")
    screen = result["candidates"][0]  # type: ignore[index]
    assert screen["entity_type"] == "Tela" and screen["title"] == "PurchaseOrderScreen"
    call = result["assertions"][0]  # type: ignore[index]
    assert call["predicate"] == "calls" and call["object_stable_key"] == "http:POST:/api/purchase-orders/1/cancel"
    assert call["object_resolution"] == "external_reference"


def test_payload_requires_explicit_base64_snapshot() -> None:
    with pytest.raises(CodeAnalysisError, match="obrigatórios"):
        analyze_code_task_payload({"revision_id": "x"})
    result = analyze_code_task_payload(
        {
            "revision_id": "00000000-0000-4000-8000-000000000101",
            "source_observation_id": "obs",
            "source_revision": "a91f3ce",
            "artifact_uri": "git://acme/checkout",
            "files": [
                {
                    "path": "src/screens/A.tsx",
                    "content_base64": base64.b64encode(b"export function A() { return null; }").decode(),
                }
            ],
        }
    )
    assert result["candidates"]
