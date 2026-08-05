# API e contratos externos

A API é a borda do produto: implementa sessão e identificação de principal, leituras de organizações, aplicações, entidades, grafo, busca, saúde, aprovações, lacunas e decisões de UX, além dos comandos idempotentes de Copiloto, aprovação, artefatos visuais e resultados de embeddings. Ela não expõe o schema PostgreSQL.

As rotas `/api/v1` usam versionamento explícito, paginação por cursor nas coleções aplicáveis, idempotência nos comandos, problemas RFC 9457, correlação, rate limit por ator+escopo e OpenAPI 3.1. Busca, grafo e Copiloto resolvem escopo e classificação antes da recuperação. Webhooks de plugins são verificados com HMAC e são idempotentes na fronteira de ingestão.

As convenções implementadas da borda estão no [Design Document da API v1](../development/design-documents/api-v1.md): versão no path, problems RFC 9457, cursor, idempotência, correlação, limite por ator+escopo, negociação e OpenAPI 3.1. O [Design Document de identidade](../design/identity-and-authorization.md) e o [ADR-010](../adr/010-identity-and-authorization.md) definem autenticação, autorização e classificação. Toda API de domínio ainda exige seu próprio Design Document com consumidores, compatibilidade, limites, retenção e depreciação.

O modelo de leitura da Home e da Visão de aplicação é implementado conforme a [API de leitura de organizações e aplicações](../development/design-documents/application-read-api.md).

O dashboard de métricas, dívida, lacunas e correlação de tickets é implementado
na [API de saúde do conhecimento](../development/design-documents/knowledge-health-api.md).

O detalhe versionado de Processo, Regra, Tela e Endpoint é implementado conforme a [API de leitura de detalhe de entidades](../development/design-documents/entity-detail-read-api.md).

A leitura das decisões de produto publicadas no grafo é implementada conforme a [API de leitura de decisões de UX](../development/design-documents/ux-decisions-read-api.md).

O modelo diagnóstico do Knowledge Graph é implementado conforme a [API de leitura do Knowledge Graph](../development/design-documents/knowledge-graph-read-api.md).

A fila e os comandos de revisão humana são implementados conforme a [API de aprovações v1](../development/design-documents/approval-api.md).

A consulta híbrida publicada, suas evidências, contagens e facetas é implementada conforme a [API de busca híbrida v1](../development/design-documents/search-api.md).

O retorno idempotente e autenticado do worker de embeddings é implementado conforme a [API de resultados de worker v1](../development/design-documents/worker-result-api.md).

O upload manual e o retorno autenticado de `artifact.reader` para prints e vídeos são implementados conforme o [Design Document de ingestão de artefatos visuais](../development/design-documents/visual-artifact-ingestion.md). Ambos exigem idempotência, autorização de `knowledge.contribute` no escopo e preservam o resultado como observação, nunca publicação automática.

A consulta RAG do Copiloto, suas citações obrigatórias e a abstenção por evidência insuficiente são implementadas conforme a [API do Copiloto v1](../development/design-documents/copilot-ask-api.md).

As propostas de FAQ e atualizações de Processo assistidas pelo Copiloto, além dos insights da Home, são implementadas conforme a [API de propostas assistidas](../development/design-documents/knowledge-candidate-api.md).

A borda planejada para receber candidates de divergência entre documentação e código também está especificada na [API de resultados de worker v1](../development/design-documents/worker-result-api.md); ela não publica correções nem decide aprovações.
