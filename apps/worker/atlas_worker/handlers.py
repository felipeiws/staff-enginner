"""Registro explícito de handlers; cada um recebe somente o contexto limitado da tarefa."""

from __future__ import annotations

from collections.abc import Callable
from typing import Any

from .artifact_reader import ArtifactReaderLimits, read_artifact_task_payload
from .client import KnowledgeEngineClient
from .code_analyzer import CodeAnalysisLimits, analyze_code_task_payload
from .content_parser import ParseLimits, parse_task_payload
from .contracts import WorkerTask
from .divergence_detector import detect_divergence_task_payload
from .embeddings import EmbeddingProvider, process_revision
from .openapi_parser import OpenApiParseLimits, parse_openapi_task_payload

Handler = Callable[[WorkerTask], None]


class HandlerRegistry:
    def __init__(self) -> None:
        self._handlers: dict[str, Handler] = {}

    def register(self, task_type: str, handler: Handler) -> None:
        if task_type in self._handlers:
            raise ValueError(f"Handler duplicado para {task_type}")
        self._handlers[task_type] = handler

    def get(self, task_type: str) -> Handler:
        try:
            return self._handlers[task_type]
        except KeyError as error:
            raise ValueError(f"Tipo de tarefa não suportado: {task_type}") from error


def embedding_handler(provider: EmbeddingProvider, client: KnowledgeEngineClient) -> Handler:
    def handle(task: WorkerTask) -> None:
        payload: dict[str, Any] = dict(task.payload)
        result = process_revision(payload, provider)
        client.submit_embedding(task, result)

    return handle


def content_parser_handler(client: KnowledgeEngineClient, limits: ParseLimits | None = None) -> Handler:
    """Devolve candidatos ao núcleo; o worker jamais aplica política ou publica."""
    def handle(task: WorkerTask) -> None:
        result = parse_task_payload(dict(task.payload), limits)
        client.submit_content_parse(task, result)

    return handle


def openapi_parser_handler(client: KnowledgeEngineClient, limits: OpenApiParseLimits | None = None) -> Handler:
    """Produz candidatos de API e relações; publicação continua sendo decisão do núcleo."""
    def handle(task: WorkerTask) -> None:
        result = parse_openapi_task_payload(dict(task.payload), limits)
        client.submit_content_parse(task, result)

    return handle


def code_analyzer_handler(client: KnowledgeEngineClient, limits: CodeAnalysisLimits | None = None) -> Handler:
    """Analisa snapshots estáticos; candidatos nunca são publicados pelo worker."""
    def handle(task: WorkerTask) -> None:
        result = analyze_code_task_payload(dict(task.payload), limits)
        client.submit_code_analysis(task, result)

    return handle


def documentation_code_divergence_handler(client: KnowledgeEngineClient) -> Handler:
    """Devolve assertions e propostas candidatas; o núcleo persiste sob política."""
    def handle(task: WorkerTask) -> None:
        result = detect_divergence_task_payload(dict(task.payload))
        client.submit_documentation_code_divergences(task, result)

    return handle


def artifact_reader_handler(client: KnowledgeEngineClient, limits: ArtifactReaderLimits | None = None) -> Handler:
    def handle(task: WorkerTask) -> None:
        client.submit_visual_artifact(task, read_artifact_task_payload(dict(task.payload), limits=limits))

    return handle
