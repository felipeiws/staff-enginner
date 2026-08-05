"""Cliente HTTP da única fronteira de retorno permitida ao Knowledge Engine."""

from __future__ import annotations

import hashlib
import json
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

from .contracts import WorkerTask


class KnowledgeEngineClient:
    def __init__(self, base_url: str, token: str, timeout_seconds: float = 20.0) -> None:
        self._base_url = base_url.rstrip("/")
        self._token = token
        self._timeout_seconds = timeout_seconds

    def submit_embedding(self, task: WorkerTask, result: dict[str, Any]) -> None:
        self._submit("/api/v1/worker-results/embeddings", task, result)

    def submit_content_parse(self, task: WorkerTask, result: dict[str, Any]) -> None:
        self._submit("/api/v1/worker-results/content-parses", task, result)

    def submit_code_analysis(self, task: WorkerTask, result: dict[str, Any]) -> None:
        self._submit("/api/v1/worker-results/code-analyses", task, result)

    def submit_documentation_code_divergences(self, task: WorkerTask, result: dict[str, Any]) -> None:
        self._submit("/api/v1/worker-results/documentation-code-divergences", task, result)

    def submit_visual_artifact(self, task: WorkerTask, result: dict[str, Any]) -> None:
        self._submit("/api/v1/worker-results/visual-artifacts", task, result)

    def _submit(self, path: str, task: WorkerTask, result: dict[str, Any]) -> None:
        payload = {**result, "task_id": task.task_id, "scope": task.scope}
        trace_id = hashlib.sha256(task.correlation_id.encode()).hexdigest()[:32]
        span_id = hashlib.sha256(task.task_id.encode()).hexdigest()[:16]
        request = Request(
            f"{self._base_url}{path}",
            data=json.dumps(payload).encode(),
            headers={
                "Authorization": f"Bearer {self._token}",
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Idempotency-Key": task.idempotency_key,
                "X-Correlation-Id": task.correlation_id,
                "traceparent": f"00-{trace_id}-{span_id}-01",
            },
            method="POST",
        )
        try:
            with urlopen(request, timeout=self._timeout_seconds) as response:  # noqa: S310 -- URL is deployment configuration.
                if response.status not in (200, 201):
                    raise RuntimeError(f"Knowledge Engine retornou HTTP {response.status}")
        except HTTPError as error:
            raise RuntimeError(f"Knowledge Engine retornou HTTP {error.code}") from error
        except URLError as error:
            raise RuntimeError("Knowledge Engine indisponível") from error
