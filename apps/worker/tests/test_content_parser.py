from __future__ import annotations

import base64
import sys
from pathlib import Path
from types import SimpleNamespace

import pytest

from atlas_worker.content_parser import ParseLimitExceeded, ParseLimits, parse_document, parse_task_payload

ROOT = Path(__file__).parents[3]
REVISION_ID = "00000000-0000-4000-8000-000000000001"
OBSERVATION_ID = "00000000-0000-4000-8000-000000000002"


def parse_fixture(path: str) -> dict[str, object]:
    return parse_document(
        (ROOT / path).read_bytes(),
        revision_id=REVISION_ID,
        source_observation_id=OBSERVATION_ID,
        source_revision="git:fixture-abc",
        artifact_uri=path,
        media_type="text/markdown",
    )


def test_parses_real_architecture_document_with_traceable_structure() -> None:
    result = parse_fixture("docs/architecture/overview.md")
    assert any(item["title"] == "Objetivo e limites" for item in result["headings"])  # type: ignore[index]
    assert result["tables"] and result["diagrams"]  # type: ignore[index]
    assert any(item["kind"] == "internal" for item in result["links"])  # type: ignore[index]
    assert result["chunks"][0]["artifact_uri"] == "docs/architecture/overview.md"  # type: ignore[index]
    assert all(candidate["lifecycle_state"] == "candidate" for candidate in result["candidates"])  # type: ignore[index]


def test_identifies_real_adr_and_prose_rules_with_exact_evidence() -> None:
    adr, rules = parse_fixture("docs/adr/007-markdown-source-of-truth.md"), parse_fixture("docs/governance/README.md")
    assert {candidate["entity_type"] for candidate in adr["candidates"]} >= {"Documento", "ADR"}  # type: ignore[index]
    rule = next(candidate for candidate in rules["candidates"] if candidate["entity_type"] == "Regra")  # type: ignore[index]
    assert rule["evidence"]["offsets"]["end"] > rule["evidence"]["offsets"]["start"]  # type: ignore[index]


def test_front_matter_links_images_code_tables_and_mermaid_are_not_followed() -> None:
    source = b"""---
title: Guia de parser
owner: plataforma
---
# Inicio

[Interno](docs/a.md) e [Externo](https://example.test/a).
![Diagrama](images/a.png)

| A | B |
| - | - |
| 1 | 2 |

```python
print("ok")
```

```mermaid
flowchart LR
 A --> B
```

## FAQ
"""
    result = parse_document(
        source,
        revision_id=REVISION_ID,
        source_observation_id=OBSERVATION_ID,
        source_revision="git:1",
        artifact_uri="docs/guia.md",
        media_type="text/markdown",
    )
    assert result["front_matter"] == {"title": "Guia de parser", "owner": "plataforma"}
    assert {item["language"] for item in result["code_blocks"]} == {"python", "mermaid"}  # type: ignore[index]
    assert result["images"][0]["target"] == "images/a.png"  # type: ignore[index]
    assert {item["kind"] for item in result["links"]} == {"internal", "external"}  # type: ignore[index]
    assert result["diagrams"][0]["kind"] == "mermaid" and any(
        candidate["entity_type"] == "FAQ" for candidate in result["candidates"]
    )  # type: ignore[index]


def test_rejects_oversize_payload_before_parsing() -> None:
    with pytest.raises(ParseLimitExceeded):
        parse_document(
            b"# grande",
            revision_id=REVISION_ID,
            source_observation_id=OBSERVATION_ID,
            source_revision="git:1",
            artifact_uri="a.md",
            media_type="text/markdown",
            limits=ParseLimits(max_bytes=2),
        )


def test_pdf_uses_extracted_page_offsets_without_network(monkeypatch: pytest.MonkeyPatch) -> None:
    class Page:
        def __init__(self, text: str) -> None:
            self._text = text

        def extract_text(self) -> str:
            return self._text

    monkeypatch.setitem(
        sys.modules,
        "pypdf",
        SimpleNamespace(
            PdfReader=lambda stream, strict: SimpleNamespace(pages=[Page("Página um"), Page("Página dois")])
        ),
    )
    result = parse_document(
        b"%PDF-fixture",
        revision_id=REVISION_ID,
        source_observation_id=OBSERVATION_ID,
        source_revision="git:pdf",
        artifact_uri="docs/fixture.pdf",
        media_type="application/pdf",
    )
    assert result["pages"] == [
        {"page": 1, "start_offset": 0, "end_offset": 9},
        {"page": 2, "start_offset": 10, "end_offset": 21},
    ]
    assert result["chunks"][0]["page"] == 1  # type: ignore[index]


def test_task_payload_is_inline_and_never_accepts_a_network_location() -> None:
    payload = {
        "revision_id": REVISION_ID,
        "source_observation_id": OBSERVATION_ID,
        "source_revision": "git:1",
        "artifact_uri": "docs/a.md",
        "content_base64": base64.b64encode(b"# local").decode(),
    }
    assert parse_task_payload(payload)["artifact_uri"] == "docs/a.md"
