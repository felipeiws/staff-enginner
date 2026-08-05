"""Embedding generation over a provider API; output remains a candidate/projection."""

from __future__ import annotations

import json
import os
from dataclasses import asdict
from datetime import UTC, datetime
from typing import Protocol
from urllib.request import Request, urlopen

from .chunking import Chunk, chunk_markdown


class EmbeddingProvider(Protocol):
    def embed(self, text: str) -> tuple[list[float], dict[str, object], float | None]: ...


class OpenAIEmbeddingProvider:
    def __init__(self, model: str, dimensions: int) -> None:
        self.model = model
        self.dimensions = dimensions

    def embed(self, text: str) -> tuple[list[float], dict[str, object], float | None]:
        api_key = os.environ["OPENAI_API_KEY"]
        payload = json.dumps({"model": self.model, "input": text, "dimensions": self.dimensions}).encode()
        request = Request(
            "https://api.openai.com/v1/embeddings",
            data=payload,
            headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
            method="POST",
        )
        with urlopen(request, timeout=30) as response:  # noqa: S310 - fixed HTTPS provider endpoint
            body = json.load(response)
        usage = body.get("usage", {})
        vector = [float(value) for value in body["data"][0]["embedding"]]
        return vector, {"input_tokens": usage.get("prompt_tokens")}, None


def process_revision(event: dict[str, object], provider: EmbeddingProvider) -> dict[str, object]:
    """Produces a versioned result for the backend application port, never a DB write."""
    revision_id = str(event["revision_id"])
    text = str(event["text"])
    model = str(event["model"])
    model_version = str(event["model_version"])
    max_tokens = _integer_option(event, "max_tokens", 800)
    overlap_tokens = _integer_option(event, "overlap_tokens", 120)
    results: list[dict[str, object]] = []
    for chunk in chunk_markdown(text, max_tokens=max_tokens, overlap_tokens=overlap_tokens):
        vector, provider_parameters, cost = provider.embed(chunk.text)
        results.append(
            {
                "ordinal": chunk.ordinal,
                "start_offset": chunk.start_offset,
                "end_offset": chunk.end_offset,
                "text": chunk.text,
                "tokens": chunk.tokens,
                "checksum": chunk.checksum,
                "embedding": vector,
                "model": model,
                "model_version": model_version,
                "parameters": {
                    "chunking": {"max_tokens": max_tokens, "overlap_tokens": overlap_tokens, "estimator": "regex-v1"},
                    "provider": provider_parameters,
                },
                "cost": cost,
                "cost_currency": "USD" if cost is not None else None,
            }
        )
    return {
        "schema_version": "1.0.0",
        "event_type": "EmbeddingGenerated",
        "revision_id": revision_id,
        "provenance": {
            "source_revision_id": revision_id,
            "method": f"embedding@{model}:{model_version}",
            "processed_at": datetime.now(UTC).isoformat().replace("+00:00", "Z"),
        },
        "chunks": results,
    }


def chunk_as_dict(chunk: Chunk) -> dict[str, object]:
    return asdict(chunk)


def _integer_option(event: dict[str, object], name: str, default: int) -> int:
    value = event.get(name, default)
    if isinstance(value, bool) or not isinstance(value, int):
        raise ValueError(f"{name} deve ser inteiro")
    return value
