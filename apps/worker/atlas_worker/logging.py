"""Logging JSON sem conteúdo de conhecimento ou credenciais."""

from __future__ import annotations

import json
import logging
from datetime import UTC, datetime
from typing import Any


class JsonFormatter(logging.Formatter):
    def format(self, record: logging.LogRecord) -> str:
        fields: dict[str, Any] = {
            "timestamp": datetime.now(UTC).isoformat(),
            "level": record.levelname.lower(),
            "message": record.getMessage(),
            "logger": record.name,
        }
        for name in ("task_id", "task_type", "correlation_id", "trace_id", "span_id", "attempt", "error"):
            value = getattr(record, name, None)
            if value is not None:
                fields[name] = value
        return json.dumps(fields, ensure_ascii=False, default=str)


def configure_logging() -> None:
    handler = logging.StreamHandler()
    handler.setFormatter(JsonFormatter())
    logger = logging.getLogger("atlas_worker")
    logger.handlers = [handler]
    logger.setLevel(logging.INFO)
    logger.propagate = False
