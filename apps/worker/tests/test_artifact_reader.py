from __future__ import annotations

import base64
import json
from hashlib import sha256
from pathlib import Path

import pytest

from atlas_worker.artifact_reader import ArtifactReaderLimits, read_artifact_task_payload


class FixtureOcr:
    def read(self, content: bytes, media_type: str) -> tuple[str, float]:
        assert media_type == "application/octet-stream"
        return "Cancelar pedido", 0.94


def fixture_payload() -> dict[str, object]:
    path = Path(__file__).parent / "fixtures" / "visual-artifact-ocr.json"
    return json.loads(path.read_text())


def test_reads_ocr_fixture_as_searchable_evidence() -> None:
    result = read_artifact_task_payload(fixture_payload(), ocr=FixtureOcr())

    assert result["event_type"] == "VisualArtifactRead"
    assert result["ocr_evidence"] == {
        "text": "Cancelar pedido",
        "method": "tesseract@5",
        "confidence": 0.94,
        "processed_at": result["ocr_evidence"]["processed_at"],
    }
    content = base64.b64decode(str(fixture_payload()["content_base64"]))
    assert result["content_hash"] == "sha256:" + sha256(content).hexdigest()


def test_requires_duration_for_videos() -> None:
    with pytest.raises(ValueError, match="duration_seconds"):
        read_artifact_task_payload({**fixture_payload(), "kind": "video"}, ocr=FixtureOcr())


def test_enforces_visual_artifact_byte_limit() -> None:
    with pytest.raises(ValueError, match="limite de tamanho"):
        read_artifact_task_payload(fixture_payload(), ocr=FixtureOcr(), limits=ArtifactReaderLimits(max_bytes=1))
