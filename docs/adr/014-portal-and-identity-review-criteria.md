# ADR-014: Critérios de revisão para Portal e identidade

Status: Accepted

## Contexto

Os ADRs [010](010-identity-and-authorization.md) e [011](011-portal-stack.md) já fixam, respectivamente, autorização antes da recuperação e a stack do Portal. Eles são imutáveis por estarem aceitos. A implementação precisa registrar critérios objetivos para reavaliar essas escolhas sem alterar retroativamente seus textos.

## Alternativas descartadas

- Alterar os ADRs aceitos adicionaria informação posterior à decisão histórica e viola a convenção do repositório.
- Reabrir ou substituir imediatamente as decisões sem evidência de falha criaria instabilidade arquitetural sem benefício.
- Tratar métricas de interface e autorização apenas como telemetria operacional não deixa explícito quando uma mudança arquitetural requer novo ADR.

## Decisão

Os ADRs 010 e 011 permanecem vigentes. Este ADR acrescenta os critérios de revisão abaixo, sem mudar suas decisões:

- **Identidade e autorização:** revisar ao introduzir SSO, MFA, delegação entre organizações, novo tipo de principal, requisito de revogação incompatível com tokens opacos ou incidente que demonstre acesso após a barreira de autorização.
- **Portal:** revisar ao trocar React/Vite/React Router/TanStack Query, exigir renderização no servidor, offline-first, microfrontends ou quando as métricas de carregamento, erros RFC 9457, acessibilidade ou regressão visual não atingirem a meta de produto acordada.

Cada revisão deve trazer ameaça ou requisito novo, impacto nos contratos, plano de compatibilidade, métricas de comparação e rollback. Mudança de decisão cria ADR sucessor que referencia o ADR afetado.

## Consequências

O histórico dos ADRs originais é preservado, enquanto evolução de identidade e Portal passa a ter gatilhos claros. Isso exige coletar as métricas já previstas no Portal e nos middleware da API, mas evita mudanças guiadas só por preferência de ferramenta.

## Critérios de revisão

Este ADR é revisado junto do primeiro ADR que substituir 010 ou 011, ou se os indicadores deixarem de ser observáveis. Não há mudança automática de stack, sessão ou autorização sem Design Document aprovado e revisão humana de arquitetura.
