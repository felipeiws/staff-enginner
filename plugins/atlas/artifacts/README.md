# Leitor de artefatos visuais

`atlas.artifacts` encaminha `artifact.read` ao worker isolado para prints, vídeos e capturas Playwright. Conteúdo inline é preferível; uma captura remota só é permitida para os hosts configurados no manifesto. OCR, miniatura, dimensões, hash, revisão e método retornam como evidência no contrato `visual-artifact-result` v1.

O plugin não publica uma Tela, não acessa armazenamento interno e não compartilha dados com plugins. O resultado é uma observação a ser submetida à política e à revisão humana. Reentregas usam `task.idempotency_key`; o host aplica a política de retry declarada.

Execute `npm test` e `npm run lint` neste diretório.
