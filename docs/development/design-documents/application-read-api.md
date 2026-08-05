# API de leitura de organizações e aplicações

## Objetivo

Oferecer os modelos de leitura que sustentam a Home e a Visão da aplicação do Portal, sem expor tabelas do PostgreSQL. Os consumidores iniciais são os blocos **Aplicações** da Home e a navegação técnica/funcional e módulos da Visão da aplicação do protótipo.

## Contrato proposto

Todas as rotas exigem principal autenticado, `knowledge.read`, classificação `interno` e seguem as convenções de `/api/v1`.

- `GET /organizations/{id}` retorna `id`, `name`, `indexed_entities_count`, `applications_count` e `provenance`.
- `GET /applications` retorna uma coleção de aplicações com `id`, `name`, `modules_count`, `screens_count`, `knowledge_health` (`score` e `status`) e `provenance`.
- `GET /home?organization={id}` compõe os dados da Home: organização, aplicações, FAQs publicados para sugestões, Processos publicados ordenados por `attributes.access_count` e atividade auditável. Cada item inclui proveniência; a composição nunca cria conhecimento ou trata a IA como autoridade.
- `GET /applications/{id}` retorna o resumo acima acrescido de `description`, `documentation_coverage`, `pending_approvals` e `provenance`.
- `GET /applications/{id}/modules` retorna `id`, `name`, `processes_count`, `apis_count`, `knowledge_health` e `provenance` para cada módulo.
- `GET /applications/{id}/tree?nav=technical|functional` retorna grupos estáveis, com `key`, `name`, `count`, `breakdown` e `provenance`. `nav` é obrigatório e aceita somente os dois valores declarados.

Os identificadores de rota são os identificadores estáveis de escopo (`organization`, `application` e `module`) já usados nas concessões de identidade. O UUID interno permanece fora do contrato HTTP. Campos são aditivos em v1.

## Modelo de leitura, segurança e proveniência

As consultas usam exclusivamente entidades publicadas e revisões publicadas do Knowledge Engine. A classificação vem de `entity_revisions.attributes.classification`, com `interno` como padrão para registros legados. Cada valor retorna a proveniência da revisão: referência de origem, revisão, método, confiança e data de processamento/observação. Agregados calculados identificam seu método como `knowledge_health_v1` ou `published_entity_aggregation_v1`.

Antes da consulta, a borda verifica uma concessão de leitura. A recuperação é limitada às combinações organização/aplicação/módulo presentes nas concessões compatíveis e ao teto de classificação; um recurso fora desse conjunto é indistinguível de inexistente. Não há acesso do Portal às tabelas ou aos modelos Eloquent.

## Operação e compatibilidade

São consultas sem efeitos colaterais e sem retenção adicional. Medir latência, erros 403/404 e o número de entidades processadas por agregação. O rollback é retirar as rotas e o modelo de leitura, sem tocar o grafo ou suas revisões. A fórmula de saúde e seus limites estão em [knowledge-health.md](../../governance/knowledge-health.md).

## Decisões relacionadas

[ADR-001](../../adr/001-laravel-backend.md), [ADR-003](../../adr/003-postgresql-system-of-record.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md), [ADR-010](../../adr/010-identity-and-authorization.md) e [API HTTP v1](api-v1.md).
