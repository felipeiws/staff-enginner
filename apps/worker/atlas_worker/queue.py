"""Adaptador Redis Streams com retry atrasado e DLQ, sem semântica de banco do núcleo."""

from __future__ import annotations

import json
import time

from redis.asyncio import Redis

from .contracts import WorkerTask


class RedisTaskQueue:
    def __init__(self, redis: Redis, stream: str, group: str, dlq_stream: str) -> None:
        self._redis = redis
        self.stream = stream
        self.group = group
        self.dlq_stream = dlq_stream
        self.retry_key = f"{stream}:retry"

    async def ensure_group(self) -> None:
        try:
            await self._redis.xgroup_create(self.stream, self.group, id="0", mkstream=True)
        except Exception as error:
            if "BUSYGROUP" not in str(error):
                raise

    async def receive(self, consumer: str, block_ms: int = 1_000) -> list[tuple[str, WorkerTask]]:
        messages = await self._redis.xreadgroup(self.group, consumer, {self.stream: ">"}, count=1, block=block_ms)
        received: list[tuple[str, WorkerTask]] = []
        for _, entries in messages:
            for message_id, fields in entries:
                raw = fields.get("task")
                if raw is None:
                    continue
                if isinstance(raw, bytes):
                    raw = raw.decode()
                received.append((str(message_id), WorkerTask.from_mapping(json.loads(str(raw)))))
        return received

    async def acknowledge(self, message_id: str) -> None:
        await self._redis.xack(self.stream, self.group, message_id)

    async def completed(self, task: WorkerTask) -> bool:
        return bool(await self._redis.exists(self._completed_key(task)))

    async def mark_completed(self, task: WorkerTask) -> None:
        await self._redis.set(self._completed_key(task), "1", ex=86_400)

    async def retry(self, task: WorkerTask, delay_seconds: float) -> None:
        retry_at_ms = int(time.time() * 1_000 + delay_seconds * 1_000)
        await self._redis.zadd(self.retry_key, {json.dumps(task.as_mapping(), separators=(",", ":")): retry_at_ms})

    async def dead_letter(self, task: WorkerTask, error: str) -> None:
        await self._redis.xadd(
            self.dlq_stream,
            {"task": json.dumps(task.as_mapping(), separators=(",", ":")), "error": error[:1_000]},
        )

    async def promote_retries(self, now_ms: int) -> int:
        due = await self._redis.zrangebyscore(self.retry_key, "-inf", now_ms)
        for raw in due:
            value = raw.decode() if isinstance(raw, bytes) else str(raw)
            async with self._redis.pipeline(transaction=True) as pipe:
                pipe.xadd(self.stream, {"task": value})
                pipe.zrem(self.retry_key, raw)
                await pipe.execute()
        return len(due)

    async def close(self) -> None:
        await self._redis.aclose()

    async def telemetry(self) -> tuple[int, float, int]:
        depth = int(await self._redis.xlen(self.stream))
        dlq_size = int(await self._redis.xlen(self.dlq_stream))
        oldest = await self._redis.xrange(self.stream, count=1)
        if not oldest:
            return depth, 0.0, dlq_size
        message_id = oldest[0][0]
        timestamp_ms = int(str(message_id).split("-", maxsplit=1)[0])
        return depth, max(0.0, time.time() - timestamp_ms / 1_000), dlq_size

    def _completed_key(self, task: WorkerTask) -> str:
        return f"{self.stream}:completed:{task.task_type}:{task.idempotency_key}"
