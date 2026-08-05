from atlas_worker.chunking import chunk_markdown


def test_chunking_is_deterministic_and_records_offsets_checksums() -> None:
    text = "\n\n".join(f"Parágrafo {index} com palavras repetidas para indexação." for index in range(50))
    first = chunk_markdown(text, max_tokens=40, overlap_tokens=8)
    second = chunk_markdown(text, max_tokens=40, overlap_tokens=8)

    assert first == second
    assert [chunk.ordinal for chunk in first] == list(range(len(first)))
    assert all(chunk.start_offset < chunk.end_offset and len(chunk.checksum) == 64 for chunk in first)


def test_chunking_keeps_code_and_tables_as_single_evidence_blocks() -> None:
    code = "```python\n" + "print('linha')\n" * 20 + "```\n"
    table = "| Campo | Valor |\n| --- | --- |\n| modelo | ativo |\n"
    chunks = chunk_markdown("Introdução.\n\n" + code + "\n" + table, max_tokens=15, overlap_tokens=3)

    assert sum("```python" in chunk.text for chunk in chunks) == 1
    assert sum("| Campo | Valor |" in chunk.text for chunk in chunks) == 1
