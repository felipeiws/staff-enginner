# Analisador Laravel

`atlas.laravel` é a distribuição do analisador estático para snapshots PHP/Laravel. Ele aceita apenas arquivos já observados pelo conector, fornecidos em `payload.files` como `path` e `content_base64`; não executa o projeto, abre caminhos locais nem resolve dependências.

Versões alvo: Laravel 10 a 13. O manifesto limita a execução a 5.000 arquivos, 20 MiB e 120 segundos. O runtime devolve candidatos e assertions em `candidate`, nunca publica no Knowledge Graph. Cada resultado conserva revisão de origem, método/versão do analisador, data de processamento e evidência `arquivo:linha` com commit.

A cobertura inicial reconhece rotas, controllers, services, jobs, eventos, listeners, migrations, models e tabelas quando há padrão estático suficiente. Relações `calls`, `reads`, `writes`, `produces`, `consumes` e `implements` são candidatas com evidência; ausência de relação não prova ausência no código.

Execute `npm test` e `npm run lint` neste diretório. A execução do analisador é coberta pelas fixtures equivalentes em `apps/worker/tests/fixtures/code/laravel` e pela suíte Python do worker.
