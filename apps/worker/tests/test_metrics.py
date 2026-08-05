from atlas_worker.metrics import WorkerMetrics


def test_renders_prometheus_metrics_without_task_payloads() -> None:
    metrics = WorkerMetrics()
    metrics.tasks["embedding.generate"] += 1
    metrics.errors["embedding.generate"] += 1

    rendered = metrics.render(in_flight=2)

    assert 'atlas_worker_tasks_total{task_type="embedding.generate"} 1' in rendered
    assert 'atlas_worker_errors_total{task_type="embedding.generate"} 1' in rendered
    assert "atlas_worker_in_flight 2" in rendered
    assert "atlas_worker_queue_depth 0" in rendered
