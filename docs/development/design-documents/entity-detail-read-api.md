# API de leitura de detalhe de entidades

## Objetivo

Oferecer `GET /api/v1/entities/{id}` como modelo de leitura autoritativo para as telas de detalhe do protótipo: Processo, Regra, Tela e Endpoint. O identificador é o UUID estável da entidade, nunca uma chave interna de uma tabela de projeção.

## Contrato proposto

A rota exige principal autenticado com `knowledge.read`; resolve escopo e classificação antes da recuperação e devolve 403 tanto para entidade inexistente quanto inacessível. Ela lê apenas uma revisão `published` e sempre inclui `provenance` (`origin`, `revision`, `method`, `confidence`, `processed_at`), `freshness` (`status`, `checked_at`, `valid_until`, `label`), responsáveis e relações publicadas qualificadas.

`projection.kind` especializa o conteúdo da revisão sem expor tabelas: `processo` contém resumo, fluxo (etapas, desvios e falhas), regras, telas, bloco técnico, código, histórico, sinais, divergências e FAQs; `regra_de_negocio` contém enunciado, vigência, aprovador, motivo, impacto, versões, diff, código, afetadas e comentário fixado; `tela` contém desktop/mobile/vídeo como referências de entidade, campos, problemas, permissões e relacionamentos; `endpoint` contém método, path, parâmetros, respostas e exemplos, métricas, consumidores e regras. Cada seção é derivada da revisão publicada indicada pela proveniência raiz; itens de relação e histórico também carregam proveniência e frescor próprios.

As métricas e inferências de IA são observações/candidatas registradas na revisão. `ai_confidence` é um sinal, não uma decisão de autoridade, e divergências não são marcadas como publicadas automaticamente.

## Operação, compatibilidade e rollback

É uma consulta sem efeito colateral. Campos de projeção são aditivos em v1; tipos não especializados recebem a projeção `generic`. Medir latência, 403 e idade da revisão devolvida. O rollback remove a rota e o modelo de leitura sem alterar entidades, revisões, assertions ou auditoria.

## Decisões relacionadas

[API HTTP v1](api-v1.md), [Knowledge Engine](../../knowledge-engine/README.md), [Governança](../../governance/README.md), [ADR-006](../../adr/006-knowledge-graph-provenance.md) e [ADR-010](../../adr/010-identity-and-authorization.md).
