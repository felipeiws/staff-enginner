# API de lacunas de conhecimento v1

## Objetivo

Materializar a decisão de UX do Copiloto: quando as evidências publicadas não
alcançam o limiar configurado, não há resposta gerada. A pergunta passa a ser
uma lacuna rastreável, agrupada por significado dentro do escopo, para alimentar
o backlog de documentação humano.

## Contrato HTTP

- `GET /api/v1/knowledge-gaps` exige `organization` e aceita `application` e
  `module`. Requer `knowledge.read` antes da recuperação e retorna `data` com
  pergunta representativa, `occurrence_count`, `period` e `severity` com o
  token de cor (`var(--warn)` ou `var(--danger)`) do card **Perguntas sem
  resposta** do protótipo.
- `POST /api/v1/knowledge-gaps/{id}/assign` requer `owner_id`, o mesmo escopo e
  `knowledge.approve`. Exige `Idempotency-Key`, atribui o responsável humano e
  abre (ou atualiza) uma tarefa de documentação com proveniência.

O Copiloto registra `copilot.abstention` para falta de fontes, confiança média
abaixo do limiar ou resposta sem citações. A busca registra `search.zero_result`.
O agrupador semântico é local e determinístico: normaliza português, remove
palavras funcionais e aplica um vocabulário pequeno de sinônimos. Ele não envia
perguntas sem resposta a modelos externos.

## Dados, governança e operação

`knowledge_gaps` é a entidade autoritativa da lacuna; ocorrências preservam a
pergunta original, origem, método, confiança, escopo, correlação e data. A janela
configurável (`KNOWLEDGE_GAPS_WINDOW_DAYS`, padrão 30) controla a contagem e o
período exibido. `KNOWLEDGE_GAPS_MINIMUM_SOURCE_COUNT` e
`KNOWLEDGE_GAPS_MINIMUM_EVIDENCE_SCORE` (padrão 1 e 0,80) controlam a abstenção.
`KNOWLEDGE_GAPS_DANGER_OCCURRENCES` (padrão 25) separa atenção de crítica.

Criação, ocorrência e atribuição passam pela porta `AuditTrail`. A tarefa não
publica conhecimento e não chama IA: ela apenas torna explícita a necessidade
de documentação e seu dono. O rollback remove as rotas; não apaga lacunas,
ocorrências, tarefas ou auditoria já registradas.

## Referências

[Copilot](../../copilot/README.md), [Busca](../../search-engine/README.md),
[Governança](../../governance/README.md), [API v1](api-v1.md),
[ADR-006](../../adr/006-knowledge-graph-provenance.md) e
[ADR-010](../../adr/010-identity-and-authorization.md).
