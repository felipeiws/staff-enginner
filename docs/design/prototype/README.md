# Protótipo normativo do portal

`atlas-prototype.html` é a referência visual normativa para o Portal Atlas. Ele é
uma cópia versionada do protótipo navegável de referência e deve orientar toda
implementação de interface: layout, hierarquia, espaçamentos, tipografia, cores,
textos de rótulo, ordem de seções, microcópia e temas claro/escuro.

A implementação compartilhada da barra lateral está em
[`apps/portal/src/components/portal-sidebar.tsx`](../../../apps/portal/src/components/portal-sidebar.tsx).
Ela consome os modelos de leitura existentes para proveniência de indexação,
contagens por árvore da aplicação e aprovações pendentes; não fixa esses valores
no Portal.

`support.js` é o arquivo de suporte necessário para abrir o protótipo. Os dois
arquivos devem ser atualizados juntos apenas quando a fonte de referência for
explicitamente revisada.

## Mapa de views

| View | Linhas em `atlas-prototype.html` |
| --- | --- |
| Sidebar | 28–66 |
| Header | 69–82 |
| Home | 84–188 |
| Busca | 190–253 |
| Copiloto | 255–355 |
| Processo | 357–511 |
| Regra | 513–591 |
| Tela | 593–664 |
| Endpoint | 666–731 |
| Aplicação | 733–791 |
| Graph | 793–849 |
| Aprovações | 851–927 |
| Saúde do conhecimento | 929–991 |
| Decisões de UX | 993–1007 |
| Command palette | 1011–1031 |
| Tokens de design | 12–27 |
| Dados de exemplo | 1034–1406 |
