"""Métricas Prometheus sem dados de conhecimento, expostas pelo processo worker."""

from __future__ import annotations

from collections import Counter


class WorkerMetrics:
    def __init__(self) -> None:
        self.tasks = Counter[str]()
        self.errors = Counter[str]()
        self.queue_depth = 0
        self.queue_oldest_age_seconds = 0.0
        self.dlq_size = 0

    def render(self, in_flight: int) -> str:
        lines = [
            "# HELP atlas_worker_tasks_total Tarefas concluídas pelo worker.",
            "# TYPE atlas_worker_tasks_total counter",
            "# HELP atlas_worker_errors_total Falhas por tipo de tarefa.",
            "# TYPE atlas_worker_errors_total counter",
            "# HELP atlas_worker_in_flight Tarefas em processamento.",
            "# TYPE atlas_worker_in_flight gauge",
            f"atlas_worker_in_flight {in_flight}",
            "# HELP atlas_worker_queue_depth Profundidade da fila de tarefas.",
            "# TYPE atlas_worker_queue_depth gauge",
            f"atlas_worker_queue_depth {self.queue_depth}",
            "# HELP atlas_worker_queue_oldest_age_seconds Idade da tarefa mais antiga da fila.",
            "# TYPE atlas_worker_queue_oldest_age_seconds gauge",
            f"atlas_worker_queue_oldest_age_seconds {self.queue_oldest_age_seconds}",
            "# HELP atlas_worker_dlq_size Mensagens na DLQ do worker.",
            "# TYPE atlas_worker_dlq_size gauge",
            f"atlas_worker_dlq_size {self.dlq_size}",
        ]
        lines.extend(f'atlas_worker_tasks_total{{task_type="{kind}"}} {count}' for kind, count in self.tasks.items())
        lines.extend(f'atlas_worker_errors_total{{task_type="{kind}"}} {count}' for kind, count in self.errors.items())
        return "\n".join(lines) + "\n"
