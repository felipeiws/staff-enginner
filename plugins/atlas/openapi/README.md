# Parser OpenAPI

`atlas.openapi` encaminha `openapi.parse` ao worker isolado para especificações OpenAPI 3.x já observadas. O parser não segue `$ref` externo nem realiza I/O de rede. Ele devolve candidatos de Aplicação, Endpoint, Schema e Evento, assertions candidatas e divergências de contrato quando recebe a revisão anterior.

As saídas usam `content-parse-result` v1, com proveniência e evidência da revisão de entrada; não publicam conteúdo nem bloqueiam revisões. Reentregas usam `task.idempotency_key` e o retry é controlado pelo host.

Execute `npm test` e `npm run lint` neste diretório.
