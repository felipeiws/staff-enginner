# @atlas/contracts

Pacote TypeScript consumível dos contratos Atlas. Os tipos em `src/generated/`
são gerados automaticamente dos JSON Schemas Draft 2020-12 em
`../contracts/schemas/v1`; não devem ser editados manualmente.

## Uso

```ts
import { GraphPredicate, isSourceObservation, validateContract } from '@atlas/contracts';

const result = validateContract('source-observation', value);
if (result.valid) {
  // result.data é SourceObservationV1
}

if (isSourceObservation(value)) {
  // value é SourceObservationV1
}

const predicate = GraphPredicate.Contains;
```

O pacote também exporta estados de ciclo de vida, predicados, famílias e tipos
de entidade do grafo, além do mapa de cores de token normativo do protótipo.
Ele depende somente de Ajv e dos schemas compartilhados; não depende de Portal,
backend ou plugins.

## Desenvolvimento

`pnpm --filter @atlas/contracts build` regenera os tipos com
`json-schema-to-typescript` e verifica TypeScript. `pnpm --filter
@atlas/contracts test` executa essa geração/verificação e os testes de validação
runtime com Ajv.
