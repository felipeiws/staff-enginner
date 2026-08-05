from atlas_worker.embeddings import process_revision


class FakeProvider:
    def embed(self, text: str) -> tuple[list[float], dict[str, object], float | None]:
        return [0.1, float(len(text))], {"input_tokens": 4}, 0.000001


def test_worker_emits_model_version_parameters_and_chunk_checksum() -> None:
    result = process_revision(
        {
            "revision_id": "rev-1",
            "text": "Texto de origem.",
            "model": "test-model",
            "model_version": "2026-07",
            "max_tokens": 10,
            "overlap_tokens": 2,
        },
        FakeProvider(),
    )

    chunk = result["chunks"][0]
    assert result["event_type"] == "EmbeddingGenerated"
    assert chunk["model"] == "test-model"
    assert chunk["model_version"] == "2026-07"
    assert len(chunk["checksum"]) == 64
    assert chunk["parameters"]["chunking"]["overlap_tokens"] == 2
