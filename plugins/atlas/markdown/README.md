# Parser Markdown

`atlas.markdown` encaminha `content.parse` ao worker isolado. Aceita somente bytes inline já observados, em Markdown ou PDF; extrai front matter, títulos, tabelas, blocos de código, Mermaid, links, imagens e chunks rastreáveis. Não abre URLs, paths locais nem escreve no Knowledge Graph.

O resultado segue `content-parse-result` v1: todo candidato permanece em `candidate` e traz evidência, revisão de origem, método, data de processamento e confiança. A chave `task.idempotency_key` torna reentregas seguras; o host aplica o retry declarado.

Execute `npm test` e `npm run lint` neste diretório.
