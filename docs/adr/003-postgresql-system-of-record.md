# ADR-003: PostgreSQL como sistema de registro

Status: Accepted

## Decisão

PostgreSQL armazena identidades, versões, assertions, evidências, políticas e auditoria. Object storage armazena blobs; outros stores são projeções.

## Alternativas e trade-off

Um grafo dedicado pode acelerar traversals específicos, mas introduz duplicação e consistência distribuída antes de conhecer os padrões reais. PostgreSQL favorece transações, backup, ferramentas e operação simples. Reavaliar quando medições provarem limitação material.
