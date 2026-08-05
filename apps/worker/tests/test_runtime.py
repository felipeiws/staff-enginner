from __future__ import annotations

import asyncio
from datetime import UTC, datetime, timedelta

from atlas_worker.contracts import WorkerTask
from atlas_worker.handlers import HandlerRegistry
from atlas_worker.runtime import WorkerRuntime


class FakeQueue:
    def __init__(self, messages: list[tuple[str, WorkerTask]]) -> None:
        self.messages = messages
        self.acknowledged: list[str] = []
        self.retried: list[tuple[WorkerTask, float]] = []
        self.dead: list[tuple[WorkerTask, str]] = []
        self.done = False
        self.runtime: WorkerRuntime | None = None

    async def ensure_group(self) -> None:
        return None

    async def promote_retries(self, now_ms: int) -> int:
        return 0

    async def receive(self, consumer: str) -> list[tuple[str, WorkerTask]]:
        result, self.messages = self.messages, []
        if not result and self.runtime is not None:
            self.runtime.stop()
        return result

    async def acknowledge(self, message_id: str) -> None:
        self.acknowledged.append(message_id)

    async def completed(self, task: WorkerTask) -> bool:
        return self.done

    async def mark_completed(self, task: WorkerTask) -> None:
        self.done = True

    async def retry(self, task: WorkerTask, delay_seconds: float) -> None:
        self.retried.append((task, delay_seconds))

    async def dead_letter(self, task: WorkerTask, error: str) -> None:
        self.dead.append((task, error))

    async def close(self) -> None:
        return None


def task(*, attempts: int = 0, deadline: datetime | None = None) -> WorkerTask:
    return WorkerTask(
        task_id="task-1",
        task_type="example",
        idempotency_key="key-1",
        correlation_id="correlation-1",
        scope={"organization": "atlas"},
        deadline_at=deadline or datetime.now(UTC) + timedelta(minutes=1),
        payload={},
        attempts=attempts,
    )


def test_runtime_executes_once_and_marks_idempotency() -> None:
    queue = FakeQueue([("1-0", task())])
    registry = HandlerRegistry()
    called: list[str] = []
    registry.register("example", lambda item: called.append(item.task_id))
    runtime = WorkerRuntime(queue, registry, "test")
    queue.runtime = runtime

    asyncio.run(runtime.run())

    assert called == ["task-1"]
    assert queue.acknowledged == ["1-0"]
    assert queue.done


def test_runtime_retries_failures_then_uses_dlq_after_limit() -> None:
    queue = FakeQueue([("1-0", task(attempts=4))])
    registry = HandlerRegistry()
    registry.register("example", lambda _: (_ for _ in ()).throw(RuntimeError("falhou")))
    runtime = WorkerRuntime(queue, registry, "test", max_attempts=5)
    queue.runtime = runtime

    asyncio.run(runtime.run())

    assert queue.acknowledged == ["1-0"]
    assert queue.retried == []
    assert queue.dead[0][0].attempts == 5


def test_runtime_does_not_execute_expired_task() -> None:
    queue = FakeQueue([("1-0", task(deadline=datetime.now(UTC) - timedelta(seconds=1)))])
    registry = HandlerRegistry()
    registry.register("example", lambda _: (_ for _ in ()).throw(AssertionError("não deveria executar")))
    runtime = WorkerRuntime(queue, registry, "test")
    queue.runtime = runtime

    asyncio.run(runtime.run())

    assert queue.dead[0][1] == "deadline da tarefa expirou"
