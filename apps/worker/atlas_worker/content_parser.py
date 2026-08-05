"""Parsing local e determinístico de documentos não confiáveis.

O módulo nunca resolve URLs, inclui arquivos ou executa conteúdo.  Ele recebe os
bytes já obtidos pelo conector e devolve observações candidatas para o núcleo.
"""

from __future__ import annotations

import base64
import re
import time
from dataclasses import dataclass
from datetime import UTC, datetime
from hashlib import sha256
from io import BytesIO
from typing import Any

from .chunking import chunk_markdown

PARSER_METHOD = "content.parser@1.0.0"
_HEADING = re.compile(r"(?m)^(#{1,6})[ \t]+(.+?)[ \t]*#?[ \t]*$")
_FENCE = re.compile(r"(?ms)^```([^\n`]*)\n(.*?)^```[ \t]*$")
_TABLE = re.compile(r"(?m)^(?:\|.*\|[ \t]*\n?){2,}")
_IMAGE = re.compile(r"!\[([^\]]*)\]\(([^ )]+)(?:\s+\"[^\"]*\")?\)")
_LINK = re.compile(r"(?<!!)\[([^\]]+)\]\(([^ )]+)(?:\s+\"[^\"]*\")?\)")
_RULE = re.compile(r"(?im)^.*\b(?:deve|devem|não pode|não devem|proibid[oa]|obrigatóri[oa])\b.*$")


class ParseLimitExceeded(ValueError):
    """O artefato ultrapassou um limite antes de uma interpretação segura."""


@dataclass(frozen=True)
class ParseLimits:
    max_bytes: int = 10 * 1024 * 1024
    timeout_seconds: float = 15.0
    max_pages: int = 500


def parse_task_payload(payload: dict[str, Any], limits: ParseLimits | None = None) -> dict[str, object]:
    """Interpreta o payload do worker sem abrir caminho de rede ou filesystem."""
    encoded = payload.get("content_base64")
    if not isinstance(encoded, str):
        raise ValueError("content_base64 é obrigatório para content.parse")
    try:
        content = base64.b64decode(encoded, validate=True)
    except ValueError as error:
        raise ValueError("content_base64 deve ser Base64 válido") from error
    required = ("revision_id", "source_observation_id", "source_revision", "artifact_uri")
    if any(not isinstance(payload.get(name), str) or not payload[name].strip() for name in required):
        raise ValueError("revision_id, source_observation_id, source_revision e artifact_uri são obrigatórios")
    return parse_document(
        content,
        revision_id=str(payload["revision_id"]),
        source_observation_id=str(payload["source_observation_id"]),
        source_revision=str(payload["source_revision"]),
        artifact_uri=str(payload["artifact_uri"]),
        media_type=str(payload.get("media_type", "text/markdown")),
        limits=limits,
    )


def parse_document(
    content: bytes,
    *,
    revision_id: str,
    source_observation_id: str,
    source_revision: str,
    artifact_uri: str,
    media_type: str,
    limits: ParseLimits | None = None,
) -> dict[str, object]:
    """Extrai estrutura e candidatos, sempre no estado ``candidate``.

    Offsets Markdown são índices de caracteres Unicode do conteúdo original.
    Em PDF, cada chunk também informa a página de origem; os offsets são do
    texto extraído daquela página, nunca um endereço em bytes instável do PDF.
    """
    active_limits = limits or ParseLimits()
    if len(content) > active_limits.max_bytes:
        raise ParseLimitExceeded("conteúdo excede o limite de tamanho do parser")
    started = time.monotonic()
    if media_type.lower() in {"application/pdf", "application/x-pdf"} or artifact_uri.lower().endswith(".pdf"):
        text, pages = _extract_pdf(content, active_limits, started)
        parsed = _parse_markdown_text(text, artifact_uri, started, active_limits)
        for chunk in parsed["chunks"]:
            chunk["page"] = _page_for_offset(int(chunk["start_offset"]), pages)
        parsed["pages"] = pages
        parsed["offset_unit"] = "character_in_extracted_page_text"
    else:
        text = content.decode("utf-8")
        parsed = _parse_markdown_text(text, artifact_uri, started, active_limits)
        parsed["offset_unit"] = "character"
    _check_timeout(started, active_limits)
    processed_at = datetime.now(UTC).isoformat().replace("+00:00", "Z")
    provenance = {
        "source_observation_id": source_observation_id,
        "source_revision": source_revision,
        "method": PARSER_METHOD,
        "processed_at": processed_at,
    }
    parsed["schema_version"] = "1.0.0"
    parsed["event_type"] = "ContentParsed"
    parsed["revision_id"] = revision_id
    parsed["artifact_uri"] = artifact_uri
    parsed["provenance"] = provenance
    for chunk in parsed["chunks"]:
        chunk["provenance"] = provenance
    parsed["candidates"] = _candidates(parsed, text, artifact_uri, provenance)
    return parsed


def _parse_markdown_text(text: str, artifact_uri: str, started: float, limits: ParseLimits) -> dict[str, Any]:
    _check_timeout(started, limits)
    front_matter, body_start = _front_matter(text)
    headings = [
        {
            "level": len(match.group(1)),
            "title": match.group(2).strip(),
            "start_offset": match.start(),
            "end_offset": match.end(),
        }
        for match in _HEADING.finditer(text)
    ]
    code_blocks: list[dict[str, object]] = []
    diagrams: list[dict[str, object]] = []
    for match in _FENCE.finditer(text):
        language = match.group(1).strip().split(maxsplit=1)[0].lower() if match.group(1).strip() else "text"
        item = {"language": language, "start_offset": match.start(), "end_offset": match.end(), "text": match.group(2)}
        code_blocks.append(item)
        if language == "mermaid":
            diagrams.append({"kind": "mermaid", **item})
    tables = [
        {"start_offset": match.start(), "end_offset": match.end(), "text": match.group(0)}
        for match in _TABLE.finditer(text)
    ]
    images = [
        {"alt": match.group(1), "target": match.group(2), "start_offset": match.start(), "end_offset": match.end()}
        for match in _IMAGE.finditer(text)
    ]
    links = [
        {
            "label": match.group(1),
            "target": match.group(2),
            "kind": "external" if _is_external(match.group(2)) else "internal",
            "start_offset": match.start(),
            "end_offset": match.end(),
        }
        for match in _LINK.finditer(text)
    ]
    chunks = [
        {
            "ordinal": chunk.ordinal,
            "start_offset": chunk.start_offset,
            "end_offset": chunk.end_offset,
            "text": chunk.text,
            "tokens": chunk.tokens,
            "checksum": chunk.checksum,
            "artifact_uri": artifact_uri,
        }
        for chunk in chunk_markdown(text)
    ]
    return {
        "front_matter": front_matter,
        "front_matter_end_offset": body_start,
        "headings": headings,
        "code_blocks": code_blocks,
        "tables": tables,
        "diagrams": diagrams,
        "links": links,
        "images": images,
        "chunks": chunks,
    }


def _front_matter(text: str) -> tuple[dict[str, object], int]:
    if not text.startswith("---\n"):
        return {}, 0
    closing = re.search(r"(?m)^---[ \t]*$", text[4:])
    if closing is None:
        return {}, 0
    end = 4 + closing.end()
    fields: dict[str, object] = {}
    for line in text[4 : 4 + closing.start()].splitlines():
        if ":" not in line or line.lstrip().startswith("#"):
            continue
        key, value = line.split(":", 1)
        fields[key.strip()] = value.strip().strip("\"'")
    return fields, end


def _candidates(
    parsed: dict[str, Any], text: str, artifact_uri: str, provenance: dict[str, str]
) -> list[dict[str, object]]:
    title = str(
        parsed["front_matter"].get("title")
        or (parsed["headings"][0]["title"] if parsed["headings"] else artifact_uri.rsplit("/", 1)[-1])
    )
    candidates = [_candidate("Documento", title, 0, len(text), text, artifact_uri, provenance)]
    if re.search(r"(?:^|/)\d{3}[-_].*\.md$", artifact_uri, re.I) or re.match(r"ADR-\d+", title, re.I):
        candidates.append(_candidate("ADR", title, 0, len(text), text, artifact_uri, provenance))
    for heading in parsed["headings"]:
        if re.search(r"\b(?:faq|perguntas frequentes)\b", str(heading["title"]), re.I):
            candidates.append(
                _candidate(
                    "FAQ",
                    str(heading["title"]),
                    int(heading["start_offset"]),
                    int(heading["end_offset"]),
                    text,
                    artifact_uri,
                    provenance,
                )
            )
    for match in _RULE.finditer(text):
        candidates.append(
            _candidate("Regra", match.group(0).strip(), match.start(), match.end(), text, artifact_uri, provenance)
        )
    return candidates


def _candidate(
    entity_type: str,
    title: str,
    start: int,
    end: int,
    text: str,
    artifact_uri: str,
    provenance: dict[str, str],
) -> dict[str, object]:
    excerpt = text[start:end]
    return {
        "entity_type": entity_type,
        "title": title,
        "stable_key": sha256(f"{entity_type}:{title}".encode()).hexdigest(),
        "lifecycle_state": "candidate",
        "confidence": 0.8 if entity_type == "Documento" else 0.72,
        "provenance": provenance,
        "evidence": {
            "kind": "chunk",
            "artifact_uri": artifact_uri,
            "offsets": {"start": start, "end": end, "unit": "character"},
            "checksum": sha256(excerpt.encode()).hexdigest(),
            "excerpt": excerpt,
        },
    }


def _extract_pdf(content: bytes, limits: ParseLimits, started: float) -> tuple[str, list[dict[str, int]]]:
    try:
        from pypdf import PdfReader  # type: ignore[import-not-found]
    except ImportError as error:  # pragma: no cover - dependency is installed in the worker image
        raise RuntimeError("suporte a PDF requer a dependência pypdf") from error
    reader = PdfReader(BytesIO(content), strict=True)
    if len(reader.pages) > limits.max_pages:
        raise ParseLimitExceeded("PDF excede o limite de páginas do parser")
    parts: list[str] = []
    pages: list[dict[str, int]] = []
    offset = 0
    for number, page in enumerate(reader.pages, start=1):
        _check_timeout(started, limits)
        page_text = page.extract_text() or ""
        parts.append(page_text)
        pages.append({"page": number, "start_offset": offset, "end_offset": offset + len(page_text)})
        offset += len(page_text) + 1
    return "\n".join(parts), pages


def _page_for_offset(offset: int, pages: list[dict[str, int]]) -> int:
    return next(
        (page["page"] for page in pages if page["start_offset"] <= offset <= page["end_offset"]),
        pages[-1]["page"] if pages else 1,
    )


def _is_external(target: str) -> bool:
    return bool(re.match(r"(?:[a-z][a-z0-9+.-]*:|//)", target, re.I))


def _check_timeout(started: float, limits: ParseLimits) -> None:
    if time.monotonic() - started > limits.timeout_seconds:
        raise ParseLimitExceeded("parser excedeu o limite de tempo")
