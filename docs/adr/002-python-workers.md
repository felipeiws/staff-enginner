# ADR-002: Python para workers especializados

Status: Accepted

## Decisão

Workers de parsing avançado, ML, embeddings e IA usarão Python e comunicarão somente contratos versionados/fila.

## Por quê

O ecossistema de análise de código, processamento de documentos e IA é mais amplo em Python. Mantê-lo fora do núcleo PHP evita comprometer escolhas transacionais por conveniência de bibliotecas.

## Consequências

Há dois runtimes e contratos precisam ser tratados como produto. O benefício é independência de escala e evolução dos modelos; não haverá RPC interno acoplado nem acesso compartilhado ao banco.
