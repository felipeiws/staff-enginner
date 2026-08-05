"""Ponto de entrada do processo worker."""

from __future__ import annotations

import asyncio
import os
import signal
import socket

from redis.asyncio import Redis

from .artifact_reader import ArtifactReaderLimits
from .client import KnowledgeEngineClient
from .code_analyzer import CodeAnalysisLimits
from .content_parser import ParseLimits
from .embeddings import OpenAIEmbeddingProvider
from .handlers import (
    HandlerRegistry,
    artifact_reader_handler,
    code_analyzer_handler,
    content_parser_handler,
    documentation_code_divergence_handler,
    embedding_handler,
    openapi_parser_handler,
)
from .health import serve_health
from .logging import configure_logging
from .metrics import WorkerMetrics
from .openapi_parser import OpenApiParseLimits
from .queue import RedisTaskQueue
from .runtime import WorkerRuntime


async def run() -> None:
    configure_logging()
    redis = Redis.from_url(os.environ.get("REDIS_URL", "redis://127.0.0.1:6379/0"), decode_responses=True)
    queue = RedisTaskQueue(
        redis,
        os.environ.get("WORKER_STREAM", "atlas:worker:tasks"),
        os.environ.get("WORKER_GROUP", "atlas-workers"),
        os.environ.get("WORKER_DLQ_STREAM", "atlas:worker:dlq"),
    )
    client = KnowledgeEngineClient(os.environ["KNOWLEDGE_ENGINE_URL"], os.environ["KNOWLEDGE_ENGINE_TOKEN"])
    provider = OpenAIEmbeddingProvider(
        os.environ.get("SEARCH_EMBEDDING_MODEL", "text-embedding-3-small"),
        int(os.environ.get("SEARCH_EMBEDDING_DIMENSIONS", "1536")),
    )
    registry = HandlerRegistry()
    registry.register("embedding.generate", embedding_handler(provider, client))
    registry.register(
        "content.parse",
        content_parser_handler(
            client,
            ParseLimits(
                max_bytes=int(os.environ.get("CONTENT_PARSER_MAX_BYTES", str(10 * 1024 * 1024))),
                timeout_seconds=float(os.environ.get("CONTENT_PARSER_TIMEOUT_SECONDS", "15")),
                max_pages=int(os.environ.get("CONTENT_PARSER_MAX_PAGES", "500")),
            ),
        ),
    )
    registry.register(
        "openapi.parse",
        openapi_parser_handler(
            client,
            OpenApiParseLimits(
                max_bytes=int(os.environ.get("OPENAPI_PARSER_MAX_BYTES", str(10 * 1024 * 1024))),
            ),
        ),
    )
    registry.register(
        "code.analyze",
        code_analyzer_handler(
            client,
            CodeAnalysisLimits(
                max_files=int(os.environ.get("CODE_ANALYZER_MAX_FILES", "5000")),
                max_bytes=int(os.environ.get("CODE_ANALYZER_MAX_BYTES", str(20 * 1024 * 1024))),
            ),
        ),
    )
    registry.register("documentation-code.detect-divergence", documentation_code_divergence_handler(client))
    registry.register(
        "artifact.read",
        artifact_reader_handler(
            client,
            ArtifactReaderLimits(max_bytes=int(os.environ.get("ARTIFACT_READER_MAX_BYTES", str(50 * 1024 * 1024)))),
        ),
    )
    metrics = WorkerMetrics()
    runtime = WorkerRuntime(queue, registry, os.environ.get("WORKER_CONSUMER", socket.gethostname()), metrics=metrics)
    health = serve_health(
        os.environ.get("WORKER_HEALTH_HOST", "0.0.0.0"),
        int(os.environ.get("WORKER_HEALTH_PORT", "8081")),
        lambda: runtime.healthy,
        lambda: runtime.in_flight,
        lambda: metrics.render(runtime.in_flight),
    )
    loop = asyncio.get_running_loop()
    for sig in (signal.SIGINT, signal.SIGTERM):
        loop.add_signal_handler(sig, runtime.stop)
    try:
        await runtime.run()
    finally:
        health.shutdown()
        await runtime.shutdown()


def main() -> None:
    asyncio.run(run())
