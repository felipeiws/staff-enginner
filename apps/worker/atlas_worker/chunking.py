"""Chunking determinístico de Markdown para a tabela de proveniência ``chunks``."""

from __future__ import annotations

import re
from dataclasses import dataclass
from hashlib import sha256

TOKEN = re.compile(r"\w+|[^\w\s]", re.UNICODE)


@dataclass(frozen=True)
class Chunk:
    ordinal: int
    start_offset: int
    end_offset: int
    text: str
    tokens: int
    checksum: str


def count_tokens(text: str) -> int:
    """A estimativa estável usada para limites; o provedor mede o custo real depois."""
    return len(TOKEN.findall(text))


def _blocks(text: str) -> list[tuple[int, int, str]]:
    """Mantém fenced code e linhas de tabela Markdown como unidades indivisíveis."""
    blocks: list[tuple[int, int, str]] = []
    for paragraph in re.finditer(r"(?ms)^```.*?^```\s*$|(?:^\|.*\|\s*$\n?)+|.+?(?:\n\s*\n|\Z)", text):
        value = paragraph.group(0)
        # A expressão de parágrafos pode cobrir prosa antes do fence; divide fences explicitamente.
        if "```" not in value or value.lstrip().startswith("```"):
            blocks.append((paragraph.start(), paragraph.end(), value))
            continue
        start = paragraph.start()
        for part in re.split(r"(?m)(?=^```)|(?<=^```\n)", value):
            if part:
                blocks.append((start, start + len(part), part))
                start += len(part)
    return blocks or [(0, len(text), text)]


def chunk_markdown(text: str, *, max_tokens: int = 800, overlap_tokens: int = 120) -> list[Chunk]:
    if max_tokens < 1 or overlap_tokens < 0 or overlap_tokens >= max_tokens:
        raise ValueError("max_tokens deve ser positivo e maior que overlap_tokens")
    pieces = _blocks(text)
    chunks: list[Chunk] = []
    current: list[tuple[int, int, str]] = []
    current_tokens = 0

    def emit() -> None:
        nonlocal current, current_tokens
        if not current:
            return
        value = "".join(item[2] for item in current)
        chunks.append(
            Chunk(
                len(chunks),
                current[0][0],
                current[-1][1],
                value,
                count_tokens(value),
                sha256(value.encode()).hexdigest(),
            )
        )
        # Overlap is only prose: code and table blocks are never duplicated or split.
        retained: list[tuple[int, int, str]] = []
        retained_tokens = 0
        for item in reversed(current):
            if item[2].lstrip().startswith("```") or item[2].lstrip().startswith("|"):
                break
            item_tokens = count_tokens(item[2])
            if retained_tokens + item_tokens > overlap_tokens:
                break
            retained.insert(0, item)
            retained_tokens += item_tokens
        current, current_tokens = retained, retained_tokens

    for piece in pieces:
        piece_tokens = count_tokens(piece[2])
        if current and current_tokens + piece_tokens > max_tokens:
            emit()
        if piece_tokens > max_tokens and not current:
            # Fidelity of a code/table block wins over the target. It remains addressable as one evidence unit.
            current = [piece]
            current_tokens = piece_tokens
            emit()
            continue
        current.append(piece)
        current_tokens += piece_tokens
    emit()
    return chunks
