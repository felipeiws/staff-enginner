from __future__ import annotations

import json
from datetime import UTC, datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread

from atlas_worker.client import KnowledgeEngineClient
from atlas_worker.contracts import WorkerTask
from atlas_worker.handlers import embedding_handler


class FakeProvider:
    def embed(self, text: str) -> tuple[list[float], dict[str, object], float | None]:
        return [0.1, 0.2], {"input_tokens": 3}, 0.000001


def test_embedding_handler_submits_a_provenance_complete_result_to_backend() -> None:
    received: dict[str, object] = {}

    class BackendHandler(BaseHTTPRequestHandler):
        def do_POST(self) -> None:  # noqa: N802
            received["path"] = self.path
            received["idempotency"] = self.headers["Idempotency-Key"]
            received["correlation"] = self.headers["X-Correlation-Id"]
            received["authorization"] = self.headers["Authorization"]
            received["body"] = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
            self.send_response(201)
            self.end_headers()

        def log_message(self, format: str, *args: object) -> None:
            return

    server = ThreadingHTTPServer(("127.0.0.1", 0), BackendHandler)
    Thread(target=server.serve_forever, daemon=True).start()
    try:
        task = WorkerTask(
            task_id="00000000-0000-4000-8000-000000000001",
            task_type="embedding.generate",
            idempotency_key="worker-result-1",
            correlation_id="00000000-0000-4000-8000-000000000002",
            scope={"organization": "atlas"},
            deadline_at=datetime.now(UTC) + timedelta(minutes=1),
            payload={
                "revision_id": "00000000-0000-4000-8000-000000000003",
                "text": "Texto de origem.",
                "model": "fixture",
                "model_version": "1",
            },
        )
        handler = embedding_handler(
            FakeProvider(), KnowledgeEngineClient(f"http://127.0.0.1:{server.server_port}", "short-lived-token")
        )
        handler(task)
    finally:
        server.shutdown()

    body = received["body"]
    assert received["path"] == "/api/v1/worker-results/embeddings"
    assert received["idempotency"] == "worker-result-1"
    assert received["correlation"] == task.correlation_id
    assert received["authorization"] == "Bearer short-lived-token"
    assert isinstance(body, dict)
    assert body["revision_id"] == task.payload["revision_id"]
    assert body["chunks"][0]["checksum"]
