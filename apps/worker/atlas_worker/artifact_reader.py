"""Leitor isolado de prints e vídeos; devolve evidência, nunca publica conhecimento."""

from __future__ import annotations

import base64
import mimetypes
from dataclasses import dataclass
from datetime import UTC, datetime
from hashlib import sha256
from io import BytesIO
from typing import Any, Protocol

ARTIFACT_READER_METHOD = "artifact.reader@1.0.0"


class OcrEngine(Protocol):
    def read(self, content: bytes, media_type: str) -> tuple[str, float]: ...


class TesseractOcr:
    """Adaptador opcional; a imagem nunca sai do runtime isolado."""

    def read(self, content: bytes, media_type: str) -> tuple[str, float]:
        if not media_type.startswith("image/"):
            return "", 0.0
        try:
            import pytesseract  # type: ignore[import-not-found]
            from PIL import Image  # type: ignore[import-not-found]
        except ImportError as error:  # pragma: no cover - depende da imagem do worker
            raise RuntimeError("OCR requer pillow e pytesseract no worker") from error
        data = pytesseract.image_to_data(Image.open(BytesIO(content)), output_type=pytesseract.Output.DICT, lang="por")
        words = [word.strip() for word in data["text"] if word.strip()]
        scores = [float(score) for score in data["conf"] if str(score) not in {"-1", ""}]
        return " ".join(words), (sum(scores) / len(scores) / 100 if scores else 0.0)


@dataclass(frozen=True)
class ArtifactReaderLimits:
    max_bytes: int = 50 * 1024 * 1024
    max_duration_seconds: int = 300


def read_artifact_task_payload(
    payload: dict[str, Any], ocr: OcrEngine | None = None, limits: ArtifactReaderLimits | None = None
) -> dict[str, object]:
    required = ("screen_key", "variant", "kind", "source_revision")
    if any(not isinstance(payload.get(key), str) or not str(payload[key]).strip() for key in required):
        raise ValueError("screen_key, variant, kind e source_revision são obrigatórios")
    if payload["variant"] not in {"desktop", "mobile"} or payload["kind"] not in {"print", "video"}:
        raise ValueError("variant ou kind inválido")
    content = _content(payload)
    active_limits = limits or ArtifactReaderLimits()
    if len(content) > active_limits.max_bytes:
        raise ValueError("artefato excede o limite de tamanho")
    media_type = str(payload.get("media_type") or _media_type(payload, content))
    duration = payload.get("duration_seconds")
    if payload["kind"] == "video" and (
        not isinstance(duration, int | float) or duration < 0 or duration > active_limits.max_duration_seconds
    ):
        raise ValueError("vídeo exige duration_seconds dentro do limite")
    text, confidence = (ocr or TesseractOcr()).read(content, media_type)
    now = datetime.now(UTC).isoformat().replace("+00:00", "Z")
    result: dict[str, object] = {
        "schema_version": "1.0.0", "event_type": "VisualArtifactRead", "screen_key": payload["screen_key"],
        "variant": payload["variant"], "kind": payload["kind"], "content_base64": base64.b64encode(content).decode(),
        "content_hash": "sha256:" + sha256(content).hexdigest(), "media_type": media_type,
        "provenance": {
            "source_revision": payload["source_revision"], "method": ARTIFACT_READER_METHOD, "processed_at": now
        },
        "ocr_evidence": {
            "text": text, "method": "tesseract@5" if text else "none", "confidence": confidence, "processed_at": now
        },
        "source_type": str(payload.get("source_type", "visual_capture")), "source_revision": payload["source_revision"],
        "artifact_at": str(payload.get("artifact_at", now)), "screen_title": payload.get("screen_title"),
        "components": payload.get("components", []), "relationships": payload.get("relationships", []),
    }
    if "code_changed_at" in payload:
        result["code_changed_at"] = payload["code_changed_at"]
    if payload["kind"] == "video":
        result["duration_seconds"] = float(duration)
    else:
        result.update(_image_details(content, media_type))
    return result


def _content(payload: dict[str, Any]) -> bytes:
    encoded = payload.get("content_base64")
    if isinstance(encoded, str):
        try:
            return base64.b64decode(encoded, validate=True)
        except ValueError as error:
            raise ValueError("content_base64 deve ser Base64 válido") from error
    capture_url = payload.get("capture_url")
    if not isinstance(capture_url, str) or not capture_url.startswith(("http://", "https://")):
        raise ValueError("content_base64 ou capture_url HTTP(S) é obrigatório")
    return capture_with_playwright(capture_url, str(payload["variant"]))


def capture_with_playwright(url: str, variant: str) -> bytes:
    """Captura determinística via Playwright; o chamador deve aplicar allowlist de egress."""
    try:
        from playwright.sync_api import sync_playwright  # type: ignore[import-not-found]
    except ImportError as error:  # pragma: no cover - depende da imagem do worker
        raise RuntimeError("captura automatizada requer playwright no worker") from error
    viewport = {"width": 390, "height": 844} if variant == "mobile" else {"width": 1440, "height": 900}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch()
        try:
            page = browser.new_page(viewport=viewport)
            page.goto(url, wait_until="networkidle")
            return page.screenshot(full_page=True, type="png")
        finally:
            browser.close()


def _media_type(payload: dict[str, Any], content: bytes) -> str:
    if content.startswith(b"\x89PNG"):
        return "image/png"
    if content.startswith(b"\xff\xd8"):
        return "image/jpeg"
    return mimetypes.guess_type(str(payload.get("filename", "")))[0] or "application/octet-stream"


def _image_details(content: bytes, media_type: str) -> dict[str, object]:
    if not media_type.startswith("image/"):
        return {}
    try:
        from PIL import Image  # type: ignore[import-not-found]
    except ImportError:
        return {}
    with Image.open(BytesIO(content)) as image:
        width, height = image.size
        thumb = image.copy()
        thumb.thumbnail((480, 480))
        stream = BytesIO()
        thumb.save(stream, format="PNG")
    return {
        "dimensions": {"width": width, "height": height},
        "thumbnail_base64": base64.b64encode(stream.getvalue()).decode(),
    }
