# Analisador React

`atlas.react` distribui o analisador estático para projetos TypeScript/React e JavaScript/React. Ele aceita somente o snapshot versionado no payload; não executa componentes, bundlers, imports ou chamadas HTTP.

Versões alvo: React 17 a 19. O manifesto limita o repositório a 5.000 arquivos, 20 MiB e 120 segundos. Componentes de tela, rotas e chamadas HTTP identificáveis produzem candidatos e assertions `candidate`, todos com origem, revisão, método, confiança e evidência `arquivo:linha · commit`.

Chamadas HTTP são referências externas até que o Knowledge Engine as resolva por contratos e evidência publicados. O plugin não acessa PostgreSQL, Redis, Typesense ou outro plugin.

Execute `npm test` e `npm run lint` neste diretório. A suíte Python em `apps/worker/tests/test_code_analyzer.py` exercita a execução estática com fixture equivalente.
