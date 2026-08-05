"""Detector determinístico de divergências entre conhecimento e comportamento de código.

O detector só compara as duas revisões recebidas na tarefa. Ele não lê o
repositório, não persiste nada e não publica correções: devolve candidatos para
o Knowledge Engine encaminhar à política e à revisão humana.
"""

from __future__ import annotations

import re
from dataclasses import dataclass
from datetime import UTC, datetime
from hashlib import sha256
from typing import Any

DETECTOR_METHOD = "documentation.code-divergence@1.0.0"


class DivergenceDetectionError(ValueError):
    """A comparação não tem revisões e proveniência suficientes."""


@dataclass(frozen=True)
class RevisionInput:
    entity_id: str
    revision_id: str
    source_revision: str
    revised_at: datetime
    processed_at: datetime
    text: str
    evidence: dict[str, Any]


def detect_divergence_task_payload(payload: dict[str, Any]) -> dict[str, object]:
    """Compara documento e comportamento já extraído do código.

    ``code.behavior`` é uma observação do analisador de código, não uma
    inferência autoritativa. Esta versão identifica a classe de conflito mais
    importante para a regra RN-142: bloqueio total documentado versus exceção
    restrita no código. Novas classes devem ser adicionadas de forma explícita
    e acompanhadas por pares de teste.
    """
    document = _revision(payload.get("document"), "document", "text")
    code = _revision(payload.get("code"), "code", "behavior")
    if code.revised_at <= document.revised_at:
        return _result(document, code, [])

    divergence = _detect_total_block_exception(document, code, payload.get("impacted_entities", []))
    return _result(document, code, [] if divergence is None else [divergence])


def _revision(value: object, name: str, text_key: str) -> RevisionInput:
    if not isinstance(value, dict):
        raise DivergenceDetectionError(f"{name} deve ser um objeto")
    fields = ("entity_id", "revision_id", "source_revision", "revised_at", "processed_at", text_key)
    if any(not isinstance(value.get(field), str) or not value[field].strip() for field in fields):
        raise DivergenceDetectionError(f"{name} exige {', '.join(fields)}")
    revised_at = _timestamp(value["revised_at"], f"{name}.revised_at")
    processed_at = _timestamp(value["processed_at"], f"{name}.processed_at")
    evidence = value.get("evidence", {})
    valid_evidence = isinstance(evidence, dict) and isinstance(evidence.get("pointer"), str)
    if not valid_evidence or not evidence["pointer"].strip():
        raise DivergenceDetectionError(f"{name}.evidence.pointer é obrigatório")
    return RevisionInput(
        entity_id=str(value["entity_id"]),
        revision_id=str(value["revision_id"]),
        source_revision=str(value["source_revision"]),
        revised_at=revised_at,
        processed_at=processed_at,
        text=str(value[text_key]),
        evidence=dict(evidence),
    )


def _timestamp(value: object, field: str) -> datetime:
    try:
        timestamp = datetime.fromisoformat(str(value).replace("Z", "+00:00"))
    except ValueError as error:
        raise DivergenceDetectionError(f"{field} deve ser ISO-8601") from error
    if timestamp.tzinfo is None:
        raise DivergenceDetectionError(f"{field} deve incluir fuso horário")
    return timestamp.astimezone(UTC)


def _detect_total_block_exception(
    document: RevisionInput, code: RevisionInput, impacted: object
) -> dict[str, object] | None:
    total_block = re.search(r"\bbloqueio\s+total\b|\bbloqueia\s+totalmente\b|\bnunca\s+permite\b", document.text, re.I)
    partial_exception = re.search(r"\bfaturamento\s+parcial\b|\bpartial\s+billing\b", code.text, re.I)
    code_exception = re.search(
        r"\b(?:ConflictException|409|exce[cç][aã]o|permite\s+cancelar\s+o\s+saldo)\b", code.text, re.I
    )
    if total_block is None or partial_exception is None or code_exception is None:
        return None

    current = document.text
    replacement = "bloqueio condicionado: faturamento parcial retorna 409 e o saldo elegível pode ser cancelado"
    proposed = current[: total_block.start()] + replacement + current[total_block.end() :]
    confidence = _confidence(document, code)
    candidate_key = sha256(f"{document.revision_id}:{code.revision_id}:{DETECTOR_METHOD}".encode()).hexdigest()
    entities = _impacted_entities(document, code, impacted)
    analysis = (
        "O código passou a tratar faturamento parcial como exceção (409), enquanto a documentação afirma "
        "bloqueio total. A sugestão é candidata e requer aprovação humana."
    )
    return {
        "candidate_key": candidate_key,
        "confidence": confidence,
        "assertion": {
            "subject_revision_id": document.revision_id,
            "predicate": "contradicts",
            "object_revision_id": code.revision_id,
            "lifecycle_state": "candidate",
            "method": DETECTOR_METHOD,
            "confidence": confidence,
            "evidence": [document.evidence, code.evidence],
        },
        "proposal": {
            "kind": "documentation_code_divergence",
            "subject_kind": "assertion",
            "assertion_candidate_key": candidate_key,
            "current_text": current,
            "proposed_text": proposed,
            "analysis": analysis,
            "impacted_entities": entities,
            "origin": {
                "code_source_revision": code.source_revision,
                "document_source_revision": document.source_revision,
                "method": DETECTOR_METHOD,
                "processed_at": code.processed_at.isoformat().replace("+00:00", "Z"),
            },
        },
    }


def _confidence(document: RevisionInput, code: RevisionInput) -> float:
    """Mantém a confiança explicável: evidência bilateral + frescor do código."""
    hours_newer = (code.revised_at - document.revised_at).total_seconds() / 3600
    freshness = min(0.08, max(0.0, hours_newer / (30 * 24) * 0.08))
    evidence_bonus = 0.02 if document.evidence.get("checksum") and code.evidence.get("checksum") else 0.0
    return round(min(0.94, 0.84 + freshness + evidence_bonus), 2)


def _impacted_entities(document: RevisionInput, code: RevisionInput, additional: object) -> list[dict[str, str]]:
    entities = [
        {"entity_id": document.entity_id, "revision_id": document.revision_id, "role": "documentação"},
        {"entity_id": code.entity_id, "revision_id": code.revision_id, "role": "código"},
    ]
    if isinstance(additional, list):
        for item in additional:
            if isinstance(item, dict) and isinstance(item.get("entity_id"), str) and isinstance(item.get("role"), str):
                entities.append(
                    {
                        "entity_id": item["entity_id"],
                        "revision_id": str(item.get("revision_id", "")),
                        "role": item["role"],
                    }
                )
    return entities


def _result(document: RevisionInput, code: RevisionInput, divergences: list[dict[str, object]]) -> dict[str, object]:
    return {
        "schema_version": "1.0.0",
        "event_type": "DocumentationCodeDivergenceDetected",
        "document_revision_id": document.revision_id,
        "code_revision_id": code.revision_id,
        "provenance": {
            "source_revision": code.source_revision,
            "method": DETECTOR_METHOD,
            "processed_at": code.processed_at.isoformat().replace("+00:00", "Z"),
        },
        "execution": {"detector_version": DETECTOR_METHOD, "kind": "deterministic_comparison", "reprocessable": True},
        "divergences": divergences,
    }
