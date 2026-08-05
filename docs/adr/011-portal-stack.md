# ADR-011: Vite, React e TypeScript estrito no Portal

Status: Accepted

## Contexto

O Portal precisa transformar os contratos e modelos de leitura da API em URLs compartilháveis, sem introduzir lógica de domínio ou acesso direto aos stores internos. A stack do Portal estava marcada como “a decidir em ADR”.

## Decisão

Usar Vite como servidor e empacotador, React 19 com TypeScript estrito para a interface, React Router para rotas reais e TanStack Query para estado assíncrono da API. O cliente HTTP do Portal consome `@atlas/contracts`, fala apenas com `/api` durante o desenvolvimento local e normaliza erros no formato RFC 9457. Vitest, Testing Library, Storybook e ESLint compõem a base de qualidade da aplicação.

As rotas iniciais são `/`, `/busca`, `/copiloto`, `/graph`, `/aplicacoes/:id`, `/processos/:id`, `/regras/:id`, `/telas/:id`, `/apis/:id`, `/aprovacoes`, `/saude` e `/decisoes-ux`. Elas são cascas de navegação até que as telas previstas nos Design Documents sejam implementadas. A página de erro/404 é a única tela deste bootstrap e segue os tokens e padrões do protótipo normativo.

## Alternativas

Manter uma página estática impediria URLs compartilháveis e a integração incremental com a API. Usar o bundler do backend acoplaria o Portal ao runtime Laravel e reduziria sua capacidade de evoluir como consumidor de contratos. Outro framework de UI foi descartado nesta fase por não oferecer ganho que justifique uma fronteira adicional.

## Consequências

O Portal passa a ter runtime Node e dependências de frontend, mas preserva a direção de dependência: ele depende do SDK de contratos e da API, nunca do banco ou dos módulos internos do backend. A autorização continua sendo aplicada pela API antes da recuperação; cache do cliente não concede acesso.

Riscos: divergência visual do protótipo e incompatibilidade entre respostas da API e contratos. Mitigações: Storybook, testes de renderização, TypeScript estrito e normalização explícita de problems. Métricas iniciais: falhas RFC 9457 por rota, tempo de carregamento da página e cobertura de testes das rotas críticas. Rollback: remover o deployment independente do Portal e manter o backend como borda autoritativa; não há migração de dados nem alteração de contrato.
