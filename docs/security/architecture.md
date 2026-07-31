# Segurança por arquitetura

Conhecimento de engenharia costuma incluir código proprietário, arquitetura, segredos acidentais e dados pessoais. Segurança é uma propriedade transversal, não uma camada colocada no fim.

- **Menor privilégio:** conectores e plugins recebem credenciais e escopos mínimos, rotacionáveis e auditáveis.
- **Zero trust entre módulos:** workers/plugins acessam contratos, não bancos internos; validação é feita em todas as bordas.
- **Classificação antes de IA/busca:** políticas definem se conteúdo pode ser indexado, exibido ou enviado a provedor externo.
- **Proveniência e auditoria:** registrar ator, decisão, origem, revisão, correlação e motivo; logs não carregam segredos ou conteúdo excessivo.
- **Conteúdo hostil:** parsing de arquivos, webhooks e URLs ocorre em ambiente isolado com limites de tamanho, tempo e rede.
- **Supply chain:** lockfiles, SBOM/scan, assinaturas e atualização controlada serão requisitos de entrega.

O modelo completo de identidades e autorização será um ADR antes da primeira interface pública. Até então, documentos devem explicitar onde um futuro boundary de acesso se aplica em vez de pressupor acesso universal.
