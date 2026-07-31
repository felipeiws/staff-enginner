# API e contratos externos

Nenhuma API é implementada nem congelada nesta fase. Quando existir, a API será uma borda de produto sobre casos de uso do Knowledge Engine, nunca um espelho do schema PostgreSQL.

Contratos públicos usarão versionamento explícito, paginação estável, idempotência para comandos, erros estruturados, correlação e documentação OpenAPI derivada de uma especificação revisada. Consultas de busca receberão escopo/filtros antes de recuperação. Webhooks serão assinados, repetíveis e entregues ao-menos-uma-vez.

O primeiro Design Document de API deve definir consumidores, autorização, classificação, compatibilidade, limites, retenção e depreciação antes de qualquer endpoint.
