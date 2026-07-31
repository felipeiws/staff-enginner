# ADR-005: Extensibilidade por plugins isolados

Status: Accepted

## Decisão

Integrações e analisadores especializados serão plugins declarativos, versionados e isolados, conectados por contratos e eventos.

## Por quê

O domínio precisa suportar ecossistemas heterogêneos sem levar dependências, credenciais e ciclos de release para o núcleo.

## Consequências

Manifestos, compatibilidade, permissões e observabilidade são obrigatórios. Aceitamos mais infraestrutura em troca de blast radius menor e contribuição Open Source sustentável.
