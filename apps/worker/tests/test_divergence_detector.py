from __future__ import annotations

import json
from datetime import UTC, datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread

from atlas_worker.client import KnowledgeEngineClient
from atlas_worker.contracts import WorkerTask
from atlas_worker.divergence_detector import DivergenceDetectionError, detect_divergence_task_payload
from atlas_worker.handlers import documentation_code_divergence_handler

DOCUMENT_ENTITY = "00000000-0000-4000-8000-000000000001"
DOCUMENT_REVISION = "00000000-0000-4000-8000-000000000002"
CODE_ENTITY = "00000000-0000-4000-8000-000000000003"
CODE_REVISION = "00000000-0000-4000-8000-000000000004"


def _payload(*, code_text: str, code_processed_at: str = "2026-07-31T13:00:00Z") -> dict[str, object]:
    return {
        "document": {
            "entity_id": DOCUMENT_ENTITY,
            "revision_id": DOCUMENT_REVISION,
            "source_revision": "docs:rn-142-v4",
            "revised_at": "2026-07-31T12:00:00Z",
            "processed_at": "2026-07-31T12:00:00Z",
            "text": "RN-142 estabelece bloqueio total para o cancelamento do pedido.",
            "evidence": {"pointer": "docs/regras.md:42", "checksum": "documento"},
        },
        "code": {
            "entity_id": CODE_ENTITY,
            "revision_id": CODE_REVISION,
            "source_revision": "a91f3ce",
            "revised_at": code_processed_at,
            "processed_at": code_processed_at,
            "behavior": code_text,
            "evidence": {"pointer": "PurchaseOrderCancelService.php:214", "checksum": "codigo", "commit": "a91f3ce"},
        },
        "impacted_entities": [{"entity_id": DOCUMENT_ENTITY, "revision_id": DOCUMENT_REVISION, "role": "regra"}],
    }


def test_detects_newer_code_that_contradicts_documentation_and_opens_candidate_proposal() -> None:
    result = detect_divergence_task_payload(
        _payload(code_text="ConflictException (409) ocorre apenas em faturamento parcial; permite cancelar o saldo.")
    )

    divergence = result["divergences"][0]  # type: ignore[index]
    assertion = divergence["assertion"]
    proposal = divergence["proposal"]
    assert assertion["predicate"] == "contradicts"
    assert assertion["subject_revision_id"] == DOCUMENT_REVISION
    assert assertion["object_revision_id"] == CODE_REVISION
    assert assertion["lifecycle_state"] == "candidate"
    assert len(assertion["evidence"]) == 2
    assert proposal["subject_kind"] == "assertion"
    assert proposal["current_text"].startswith("RN-142")
    assert "bloqueio condicionado" in proposal["proposed_text"]
    assert "aprovação humana" in proposal["analysis"]
    assert {item["entity_id"] for item in proposal["impacted_entities"]} == {DOCUMENT_ENTITY, CODE_ENTITY}
    assert 0 < divergence["confidence"] <= 1


def test_does_not_open_proposal_for_convergent_or_older_code() -> None:
    convergent = detect_divergence_task_payload(_payload(code_text="O cancelamento continua sob bloqueio total."))
    older = detect_divergence_task_payload(
        _payload(
            code_text="ConflictException (409) ocorre apenas em faturamento parcial.",
            code_processed_at="2026-07-31T11:00:00Z",
        )
    )

    assert convergent["divergences"] == []
    assert older["divergences"] == []


def test_requires_provenance_and_evidence_for_both_revisions() -> None:
    payload = _payload(code_text="409 em faturamento parcial")
    del payload["code"]["evidence"]  # type: ignore[index]

    try:
        detect_divergence_task_payload(payload)
    except DivergenceDetectionError as error:
        assert "evidence.pointer" in str(error)
    else:
        raise AssertionError("a ausência de evidência deveria impedir a detecção")


def test_handler_submits_only_candidate_divergences_to_knowledge_engine() -> None:
    received: dict[str, object] = {}

    class BackendHandler(BaseHTTPRequestHandler):
        def do_POST(self) -> None:  # noqa: N802
            received["path"] = self.path
            received["body"] = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
            self.send_response(201)
            self.end_headers()

        def log_message(self, format: str, *args: object) -> None:
            return

    server = ThreadingHTTPServer(("127.0.0.1", 0), BackendHandler)
    Thread(target=server.serve_forever, daemon=True).start()
    try:
        task = WorkerTask(
            task_id="00000000-0000-4000-8000-000000000010",
            task_type="documentation-code.detect-divergence",
            idempotency_key="divergence-result-1",
            correlation_id="00000000-0000-4000-8000-000000000011",
            scope={"organization": "atlas"},
            deadline_at=datetime.now(UTC) + timedelta(minutes=1),
            payload=_payload(
                code_text="ConflictException (409) ocorre apenas em faturamento parcial; permite cancelar o saldo."
            ),
        )
        documentation_code_divergence_handler(
            KnowledgeEngineClient(f"http://127.0.0.1:{server.server_port}", "short-lived-token")
        )(task)
    finally:
        server.shutdown()

    body = received["body"]
    assert received["path"] == "/api/v1/worker-results/documentation-code-divergences"
    assert isinstance(body, dict)
    assert body["divergences"][0]["assertion"]["lifecycle_state"] == "candidate"
