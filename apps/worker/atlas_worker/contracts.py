"""Contratos de execução do worker; nenhum tipo expõe modelos do banco do núcleo."""

from __future__ import annotations

from collections.abc import Mapping
from dataclasses import dataclass
from datetime import UTC, datetime
from typing import Any


class InvalidTask(ValueError):
    """A mensagem não pode ser executada com segurança."""


@dataclass(frozen=True)
class WorkerTask:
    task_id: str
    task_type: str
    idempotency_key: str
    correlation_id: str
    scope: dict[str, str]
    deadline_at: datetime
    payload: dict[str, Any]
    attempts: int = 0

    @classmethod
    def from_mapping(cls, value: Mapping[str, Any]) -> WorkerTask:
        required = ("task_id", "task_type", "idempotency_key", "correlation_id", "scope", "deadline_at", "payload")
        if any(not isinstance(value.get(key), str) or not str(value[key]).strip() for key in required[:4]):
            raise InvalidTask("task_id, task_type, idempotency_key e correlation_id são obrigatórios")
        valid_scope = isinstance(value["scope"], dict) and all(
            isinstance(key, str) and isinstance(item, str) for key, item in value["scope"].items()
        )
        if not valid_scope:
            raise InvalidTask("scope deve conter apenas pares string")
        if not isinstance(value["payload"], dict):
            raise InvalidTask("payload deve ser um objeto")
        try:
            deadline = datetime.fromisoformat(str(value["deadline_at"]).replace("Z", "+00:00"))
        except ValueError as error:
            raise InvalidTask("deadline_at deve ser ISO-8601") from error
        if deadline.tzinfo is None:
            raise InvalidTask("deadline_at deve incluir fuso horário")
        attempts = value.get("attempts", 0)
        if not isinstance(attempts, int) or attempts < 0:
            raise InvalidTask("attempts deve ser inteiro não negativo")
        return cls(
            task_id=str(value["task_id"]),
            task_type=str(value["task_type"]),
            idempotency_key=str(value["idempotency_key"]),
            correlation_id=str(value["correlation_id"]),
            scope=dict(value["scope"]),
            deadline_at=deadline.astimezone(UTC),
            payload=dict(value["payload"]),
            attempts=attempts,
        )

    def deadline_seconds(self, now: datetime) -> float:
        return (self.deadline_at - now.astimezone(UTC)).total_seconds()

    def as_mapping(self) -> dict[str, Any]:
        return {
            "schema_version": "1.0.0",
            "task_id": self.task_id,
            "task_type": self.task_type,
            "idempotency_key": self.idempotency_key,
            "correlation_id": self.correlation_id,
            "scope": self.scope,
            "deadline_at": self.deadline_at.isoformat().replace("+00:00", "Z"),
            "payload": self.payload,
            "attempts": self.attempts,
        }
