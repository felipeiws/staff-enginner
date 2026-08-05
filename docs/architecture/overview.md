# Arquitetura de referência

## Objetivo e limites

O sistema converte artefatos de engenharia em uma malha de conhecimento consultável. O resultado deve manter ligação com a fonte, revisão e método que o produziu. A plataforma armazena e relaciona conhecimento; não assume posse do código ou substitui sistemas de origem.

O início é um monólito modular no backend, com workers separados apenas onde o runtime e o perfil de carga justificam Python. Isso reduz custo operacional sem fechar a porta para separação futura: fronteiras são contratos/eventos, não processos distribuídos prematuros.

O bootstrap atual está em [`apps/backend`](../../apps/backend/README.md): Laravel 13, com módulos `KnowledgeEngine`, `Search`, `Governance`, `Ingestion`, `Copilot` e `Plugins`. Cada módulo expõe provider próprio e preserva as camadas `Domain`, `Application`, `Infrastructure` e `Http`; a suíte de arquitetura protege essas fronteiras. O Portal em [`apps/portal`](../../apps/portal) usa Vite, React 19 e TypeScript estrito como consumidor da API e de `@atlas/contracts`, conforme o [ADR-011](../adr/011-portal-stack.md).

## Módulos e responsabilidades

| Módulo           | Faz                                                                          | Não faz                                                         |
| ---------------- | ---------------------------------------------------------------------------- | --------------------------------------------------------------- |
| Portal           | navegação, revisão, visualização e administração                             | regras centrais ou acesso direto a dados                        |
| API              | borda estável, validação/orquestração e autorização por escopo               | parsing pesado ou lógica duplicada do núcleo                    |
| Knowledge Engine | identidade, fontes, versões, evidências, relações, ciclo de vida e auditoria | ranking de busca, execução de modelos ou detalhes de conectores |
| Search Engine    | projeções recuperáveis, busca híbrida, filtros e ranking                     | fonte de verdade ou mutação do grafo                            |
| Workers          | extração, chunking, embeddings, classificação e tarefas longas               | decidir publicação final sem política do núcleo                 |
| Plugins          | adaptação de sistemas externos e tipos especializados                        | depender de tabelas internas ou uns dos outros                  |
| Storage          | conteúdo bruto e artefatos grandes                                           | semântica do domínio                                            |

```mermaid
flowchart LR
  Portal --> API
  API --> KE[Knowledge Engine]
  KE --> PG[(PostgreSQL)]
  KE --> Redis
  Redis --> Worker[Python workers]
  Worker --> KE
  KE --> Index[Typesense projection]
  Plugin --> KE
  Blob[Object storage] --> KE
```

## Comunicação e dependências

Operações síncronas servem apenas para comandos/consultas curtas. Mudanças relevantes emitem eventos de domínio com `event_id`, versão do schema, `occurred_at`, `correlation_id`, ator, escopo e referência à entidade/revisão. Consumidores são idempotentes; entrega é ao-menos-uma-vez. O backend materializa a outbox transacional: a mudança do agregado e seu envelope validado pelo contrato `domain-event.json` são gravados na mesma transação. Um dispatcher reivindica lotes, publica no Redis e aplica retry com backoff, DLQ e reprocessamento. A ordenação é preservada por agregado, e `processed_events` registra `(consumer, event_id)` para tolerar reentrega.

O runtime em [`apps/worker`](../../apps/worker) consome Redis Streams por grupo de consumidores. Toda tarefa transporta `task_id`, tipo, chave de idempotência, `correlation_id`, escopo limitado e deadline; o executor limita o tempo, reenvia com backoff exponencial e move a mensagem para uma DLQ após o limite. O worker devolve dados apenas à API documentada do Knowledge Engine com token de máquina de curta duração; não possui credenciais PostgreSQL.

Contratos vivem em `packages/contracts`; cada contrato é versionado semanticamente e tolerante a campos desconhecidos. APIs públicas só são adicionadas após estabilização do contrato interno. Plugins e workers usam IDs opacos, nunca chaves internas de banco. A autorização de escopo e classificação é resolvida antes de qualquer recuperação protegida, conforme o [ADR-010](../adr/010-identity-and-authorization.md).

## Auditoria transversal

O contrato `AuditTrail` é a única fronteira de escrita da trilha para ingestão, ciclo de vida, políticas e aprovações. Ele persiste no PostgreSQL no mesmo limite transacional da mutação e sanitiza snapshots antes de gravá-los. Busca e Copiloto usam a mesma fronteira quando produzirem decisões auditáveis; não há acesso direto ao `audit_log` por esses módulos. A consulta por entidade é um modelo de leitura autoritativo para o Histórico do Portal.

## Fluxo de indexação

```mermaid
sequenceDiagram
  participant C as Conector/Plugin
  participant K as Knowledge Engine
  participant Q as Fila
  participant W as Worker
  participant S as Search
  C->>K: observação + origem + revisão
  K->>K: valida, deduplica, versiona, audita
  K->>Q: KnowledgeRevisionCreated
  Q->>W: tarefa idempotente
  W->>K: candidatos, chunks, embeddings, evidências
  K->>K: aplica política/aprovação
  K->>S: projeção publicada
```

## Fluxo de aprovação e IA

```mermaid
flowchart LR
  I[Inferência/extração] --> C[Candidato com confiança e evidência]
  C --> P{Política permite publicação automática?}
  P -- não --> R[Revisão humana]
  R -->|aprova| V[Versão publicada]
  R -->|rejeita| H[Histórico de decisão]
  P -- sim --> V
  V --> A[Auditoria + projeção de busca]
```

IA pode sugerir relações, sínteses ou respostas; deve devolver citações de revisões e configuração de execução. O grafo não aceita uma afirmação sem proveniência ou marcação explícita de hipótese.

## Evolução e trade-offs

PostgreSQL é o sistema de registro para consistência, auditoria e consultas relacionais. Typesense é deliberadamente derivado: sacrifica consultas transacionais em troca de recuperação de baixa latência. O modelo relacional de grafo começa simples; um banco de grafos separado só será considerado após métricas mostrarem que traversals especializados superam a complexidade de duplicação.
