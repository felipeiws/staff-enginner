"""Loop operacional: entrega ao-menos-uma-vez, prazo, backoff, DLQ e shutdown gracioso."""

from __future__ import annotations

import asyncio
import hashlib
import logging
import time
from collections.abc import Callable
from datetime import UTC, datetime

from .contracts import WorkerTask
from .handlers import HandlerRegistry
from .metrics import WorkerMetrics
from .queue import RedisTaskQueue

LOG = logging.getLogger("atlas_worker.runtime")


class WorkerRuntime:
    def __init__(
        self,
        queue: RedisTaskQueue,
        registry: HandlerRegistry,
        consumer: str,
        max_attempts: int = 5,
        metrics: WorkerMetrics | None = None,
    ) -> None:
        self._queue = queue
        self._registry = registry
        self._consumer = consumer
        self._max_attempts = max_attempts
        self._stopping = False
        self._in_flight = 0
        self.metrics = metrics or WorkerMetrics()

    @property
    def healthy(self) -> bool:
        return not self._stopping

    @property
    def in_flight(self) -> int:
        return self._in_flight

    def stop(self) -> None:
        self._stopping = True

    async def run(self, now: Callable[[], datetime] | None = None) -> None:
        clock = now or (lambda: datetime.now(UTC))
        await self._queue.ensure_group()
        while not self._stopping:
            queue_depth, queue_oldest_age_seconds, dlq_size = await self._queue.telemetry()
            self.metrics.queue_depth = queue_depth
            self.metrics.queue_oldest_age_seconds = queue_oldest_age_seconds
            self.metrics.dlq_size = dlq_size
            await self._queue.promote_retries(int(time.time() * 1_000))
            for message_id, task in await self._queue.receive(self._consumer):
                if self._stopping:
                    break
                self._in_flight += 1
                try:
                    await self._process(message_id, task, clock())
                finally:
                    self._in_flight -= 1

    async def _process(self, message_id: str, task: WorkerTask, now: datetime) -> None:
        extra = {
            "task_id": task.task_id,
            "task_type": task.task_type,
            "correlation_id": task.correlation_id,
            "attempt": task.attempts,
            "trace_id": hashlib.sha256(task.correlation_id.encode()).hexdigest()[:32],
            "span_id": hashlib.sha256(task.task_id.encode()).hexdigest()[:16],
        }
        try:
            if task.deadline_seconds(now) <= 0:
                raise TimeoutError("deadline da tarefa expirou")
            if await self._queue.completed(task):
                await self._queue.acknowledge(message_id)
                LOG.info("tarefa repetida ignorada", extra=extra)
                return
            handler = self._registry.get(task.task_type)
            async with asyncio.timeout(task.deadline_seconds(now)):
                await asyncio.to_thread(handler, task)
            await self._queue.mark_completed(task)
            await self._queue.acknowledge(message_id)
            self.metrics.tasks[task.task_type] += 1
            LOG.info("tarefa concluída", extra=extra)
        except Exception as error:
            self.metrics.errors[task.task_type] += 1
            await self._queue.acknowledge(message_id)
            next_task = WorkerTask(**{**task.__dict__, "attempts": task.attempts + 1})
            if next_task.attempts >= self._max_attempts or isinstance(error, TimeoutError):
                await self._queue.dead_letter(next_task, str(error))
                LOG.error("tarefa enviada para DLQ", extra={**extra, "error": str(error)})
                return
            delay = min(300.0, 2.0**next_task.attempts)
            await self._queue.retry(next_task, delay)
            LOG.warning("tarefa agendada para nova tentativa", extra={**extra, "error": str(error)})

    async def shutdown(self) -> None:
        self.stop()
        await self._queue.close()
