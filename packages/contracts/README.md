# Contratos compartilhados

Este pacote contém contratos de fronteira entre Portal, API, Knowledge Engine, workers e plugins. Os schemas em [schemas/v1](schemas/v1) usam JSON Schema Draft 2020-12 e são deliberadamente tolerantes a campos desconhecidos (`additionalProperties: true`), para permitir evolução aditiva sem quebrar consumidores.

## Uso e garantias

Cada comando representado nos contratos inclui `command.idempotency_key` e `command.actor`; toda mutação deve usar esses valores para deduplicação e auditoria. Os dados de conhecimento incluem proveniência — revisão de origem, método, data de processamento e, quando aplicável, confiança. Assertions de IA permanecem em estados candidatos até a aprovação humana ou política explicitamente aplicável.

Os estados de ciclo de vida são: `observed`, `draft`, `candidate`, `review`, `published`, `superseded` e `retracted`. `observed` representa uma recepção íntegra de fonte antes de normalização e curadoria.

O envelope [domain-event.json](schemas/v1/domain-event.json) contém `event_id`, `schema_version`, `occurred_at`, `correlation_id`, ator, escopo e referência de entidade/revisão. A entrega é ao-menos-uma-vez, portanto consumidores devem ser idempotentes por `event_id`.

O [worker-task.json](schemas/v1/worker-task.json) define tarefas Redis com deadline, escopo limitado e chave de idempotência. O [embedding-result.json](schemas/v1/embedding-result.json) define o retorno de embeddings com revisão de origem, método, processamento e chunks verificáveis; ele é aceito exclusivamente pela API do núcleo.

O [content-parse-result.json](schemas/v1/content-parse-result.json) define o retorno de `content.parse`: estrutura extraída, chunks rastreáveis e candidatos de conhecimento. Os candidatos são sempre `candidate`, com evidência, origem, revisão, método e confiança; a borda do Knowledge Engine os encaminha ao motor de políticas, nunca os publica diretamente.

O [documentation-code-divergence-result.json](schemas/v1/documentation-code-divergence-result.json) define o retorno de `documentation-code.detect-divergence`. Ele contém somente assertions `contradicts` e propostas de aprovação candidatas, cada qual ancorada nas revisões de documento e código, suas evidências, análise, diff, confiança e entidades impactadas. A API persiste e encaminha esses candidatos para política e revisão humana; o worker nunca corrige documentação.

O [plugin-manifest.json](schemas/v1/plugin-manifest.json) define o manifesto declarativo de plugins: ID imutável, SemVer, compatibilidade da plataforma, runtime isolado, capacidades, configurações, permissões, schemas de E/S, limites e retry. O host aceita somente observações e evidências como retorno; plugins não escrevem no grafo.

## Vocabulário inicial de predicados

| Predicado     | Semântica inversa         |
| ------------- | ------------------------- |
| `contains`    | `belongs_to`              |
| `belongs_to`  | `contains`                |
| `implements`  | `implemented_by`          |
| `calls`       | `called_by`               |
| `exposes`     | `exposed_by`              |
| `consumes`    | `produces`                |
| `produces`    | `consumes`                |
| `reads`       | `written_by`              |
| `writes`      | `read_by`                 |
| `governs`     | `governed_by`             |
| `documents`   | `documented_by`           |
| `depends_on`  | `depended_on_by`          |
| `supersedes`  | `superseded_by`           |
| `owned_by`    | `owns`                    |
| `related_to`  | `related_to` (simétrico)  |
| `contradicts` | `contradicts` (simétrico) |

Os nomes inversos que não estão no vocabulário fechado são semântica de consulta/documentação; não autorizam novos valores em `assertion.predicate`. A inclusão de outro predicado requer versão de contrato e definição explícita de inversão.

## Política de versionamento

Os contratos seguem SemVer. O diretório `v1` identifica a versão principal e o campo `schema_version` identifica a versão SemVer do documento. Uma versão **PATCH** só corrige descrições ou restrições que não alterem instâncias válidas; uma **MINOR** só adiciona campos opcionais, valores não disruptivos documentados ou esclarecimentos compatíveis; uma **MAJOR** pode remover/renomear campos, tornar campo obrigatório ou alterar semântica. Campos desconhecidos devem ser preservados pelos intermediários quando possível. Mudanças maiores criam `schemas/v<N>/` e permanecem coexistindo durante a janela de migração.

Execute `pnpm --filter @atlas/contracts-schemas test` para validar os exemplos válidos e inválidos contra todos os schemas. O pacote TypeScript consumível é `@atlas/contracts`, em `packages/sdk`.

## SDK TypeScript

O [SDK `@atlas/contracts`](../sdk/README.md) gera seus tipos automaticamente a
partir destes schemas no build, valida instâncias em runtime com Ajv e expõe
type guards e vocabulários estáveis do grafo. Ele é um consumidor dos contratos:
não depende de Portal, backend ou plugins.

## SDK PHP

O [SDK `atlas/contracts`](../contracts-php/README.md) oferece DTOs `readonly`
para PHP 8.3, factories de desserialização, validação runtime com
`opis/json-schema` e enums nativos do vocabulário do grafo. Ele não depende de
Laravel nem de banco de dados.
