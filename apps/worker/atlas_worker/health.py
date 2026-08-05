"""Endpoint HTTP mínimo para orquestradores, sem expor tarefas ou segredos."""

from __future__ import annotations

import json
from collections.abc import Callable
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from threading import Thread


def serve_health(
    host: str,
    port: int,
    healthy: Callable[[], bool],
    in_flight: Callable[[], int],
    metrics: Callable[[], str] | None = None,
) -> ThreadingHTTPServer:
    class HealthHandler(BaseHTTPRequestHandler):
        def do_GET(self) -> None:  # noqa: N802 - método da stdlib
            if self.path == "/metrics" and metrics is not None:
                payload = metrics().encode()
                self.send_response(200)
                self.send_header("Content-Type", "text/plain; version=0.0.4; charset=utf-8")
                self.send_header("Content-Length", str(len(payload)))
                self.end_headers()
                self.wfile.write(payload)
                return
            if self.path != "/health":
                self.send_error(404)
                return
            is_healthy = healthy()
            payload = json.dumps({"status": "ok" if is_healthy else "stopping", "in_flight": in_flight()}).encode()
            self.send_response(200 if is_healthy else 503)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

        def log_message(self, format: str, *args: object) -> None:
            return

    server = ThreadingHTTPServer((host, port), HealthHandler)
    Thread(target=server.serve_forever, daemon=True).start()
    return server
