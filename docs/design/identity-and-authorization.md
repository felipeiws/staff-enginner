# Identidade e autorização por escopo

## Problema e objetivo

O Portal e as futuras APIs precisam identificar pessoas e máquinas sem presumir
acesso universal ao conhecimento. O objetivo é autenticar pessoas por sessão,
autenticar CI, plugins e workers por token opaco e curto, e decidir acesso por
organização, aplicação, módulo, permissão e classificação antes da recuperação.

## Não objetivos

Este desenho não escolhe um provedor corporativo de SSO, não cria uma interface
de administração de membros e não torna o índice de busca uma fonte de decisão.

## Contexto e evidências

O [Search Engine](../search-engine/README.md) exige filtros e autorização antes
do ranking; a [Governança](../governance/README.md) já define classificações de
conteúdo. O cabeçalho normativo do protótipo inclui avatar e menu de usuário.

## Proposta e contratos

O módulo `Identity` expõe uma porta de autorização. Um principal autenticado
possui concessões de escopo (`organization`, `application`, `module`),
permissões explícitas e teto de classificação. Papéis iniciais são `leitor`,
`contribuidor`, `aprovador_de_dominio` e `administrador_de_plataforma`; eles são
atalhos estáveis para permissões, nunca substitutos da avaliação do escopo.

Pessoas usam a guarda de sessão do Laravel. Máquinas recebem um token aleatório
opaco, persistido somente como hash SHA-256, com expiração máxima configurável
de 15 minutos. CI, plugins e workers devem declarar permissões e escopo ao
emitir o token. O token em claro só é retornado na emissão.

O método `authorizeBeforeRetrieval` resolve e nega permissões, escopo e
classificação antes de chamar a recuperação protegida. Consumidores de busca,
grafo e RAG devem usar esta porta; o resultado nunca é filtrado depois de uma
recuperação ampla.

## Alternativas consideradas

Usar apenas papéis globais falha em separar aplicações e módulos. Filtrar
resultados depois da busca pode revelar termos, contagens e metadados. JWTs
autoportantes exigiriam revogação e rotação adicionais nesta primeira fase;
tokens opacos curtos simplificam ambos.

## Dados, segurança e privacidade

Concessões são vinculadas a usuário ou credencial de máquina. Tokens não são
auditados em claro, são expirados e podem ser revogados. A classificação faz
parte da decisão de acesso e não pode ser elevada por um papel inferior.

## Operação: métricas, alertas e falhas

Devem ser medidos tokens expirados/revogados, recusas por escopo/classificação
e latência da autorização. Falha ao resolver a concessão nega acesso.

## Compatibilidade, migração e rollback

A migration adiciona somente tabelas de identidade. Rollback remove as
credenciais e concessões, após revogação operacional dos tokens emitidos.

## Riscos e questões em aberto

SSO, MFA, rotação automática e delegação temporária permanecem evoluções
planejadas. A interface administrativa requer um Design Document próprio.

## Decisões/ADRs relacionados

[ADR-003](../adr/003-postgresql-system-of-record.md),
[ADR-005](../adr/005-plugin-boundaries.md),
[ADR-006](../adr/006-knowledge-graph-provenance.md) e
[ADR-010](../adr/010-identity-and-authorization.md).
