from __future__ import annotations

import base64
import json
from datetime import UTC, datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread

from atlas_worker.client import KnowledgeEngineClient
from atlas_worker.contracts import WorkerTask
from atlas_worker.handlers import content_parser_handler


def test_content_parser_handler_submits_candidates_to_the_policy_boundary() -> None:
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
            task_id="00000000-0000-4000-8000-000000000011",
            task_type="content.parse",
            idempotency_key="parse-result-1",
            correlation_id="00000000-0000-4000-8000-000000000012",
            scope={"organization": "atlas"},
            deadline_at=datetime.now(UTC) + timedelta(minutes=1),
            payload={
                "revision_id": "00000000-0000-4000-8000-000000000013",
                "source_observation_id": "00000000-0000-4000-8000-000000000014",
                "source_revision": "git:abc",
                "artifact_uri": "docs/fixture.md",
                "content_base64": base64.b64encode(b"# Documento\n\nNao pode publicar.").decode(),
            },
        )
        content_parser_handler(KnowledgeEngineClient(f"http://127.0.0.1:{server.server_port}", "short-lived-token"))(
            task
        )
    finally:
        server.shutdown()

    body = received["body"]
    assert received["path"] == "/api/v1/worker-results/content-parses"
    assert isinstance(body, dict) and body["event_type"] == "ContentParsed"
    assert all(item["lifecycle_state"] == "candidate" for item in body["candidates"])
