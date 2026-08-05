# ADR-010: Identidade e autorização antes da recuperação

Status: Accepted

## Contexto

O ADR-009 já está aceito para o ciclo de vida `observed`; por isso este ADR usa
o próximo identificador disponível. A plataforma passa a expor autenticação e
precisa evitar que busca, grafo ou IA recuperem conteúdo fora do escopo ou da
classificação do ator.

## Decisão

Pessoas autenticam no Portal por sessão. CI, plugins e workers autenticam por
token opaco de curta duração, com permissões e escopos explícitos. A autorização
combina papel, organização, aplicação, módulo e teto de classificação. A porta
de autorização é chamada antes da recuperação protegida e nega por padrão.

Os papéis iniciais são `leitor`, `contribuidor`, `aprovador_de_dominio` e
`administrador_de_plataforma`. Papéis não ignoram classificação nem concedem
acesso global quando a concessão é limitada.

## Alternativas

Papéis globais não representam a separação entre organizações e aplicações.
Filtrar após recuperar revela informação. JWTs de longa duração pioram revogação
e rotação nesta fase inicial.

## Consequências

Consultas de Search, Knowledge Engine e RAG passam a depender da porta de
autorização. Tokens precisam de emissão, expiração e revogação controladas.
SSO e MFA continuam planejados.
