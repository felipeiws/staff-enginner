# ADR-001: Laravel como backend modular

Status: Accepted

## Contexto

O núcleo exige regras consistentes de versionamento, auditoria, políticas e orquestração, sem justificar microserviços no início.

## Decisão

Usar Laravel 13/PHP para API e Knowledge Engine, organizados como monólito modular com fronteiras explícitas de domínio.

## Alternativas

Framework Python único reduziria linguagens, mas não é a escolha mais produtiva para a camada transacional proposta. Microserviços em linguagens múltiplas isolariam cedo demais e aumentariam deployment, tracing e contratos.

## Consequências

Ganhamos ecossistema maduro, produtividade e transações coesas. Aceitamos disciplina para impedir que módulos Laravel virem acoplamento interno; tarefas computacionais permanecem fora do request path.
