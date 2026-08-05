# Segurança por arquitetura

Conhecimento de engenharia costuma incluir código proprietário, arquitetura, segredos acidentais e dados pessoais. Segurança é uma propriedade transversal, não uma camada colocada no fim.

- **Menor privilégio:** conectores e plugins recebem credenciais e escopos mínimos, rotacionáveis e auditáveis.
- **Zero trust entre módulos:** workers/plugins acessam contratos, não bancos internos; validação é feita em todas as bordas.
- **Classificação antes de IA/busca:** políticas definem se conteúdo pode ser indexado, exibido ou enviado a provedor externo.
- **Proveniência e auditoria:** registrar ator, decisão, origem, revisão, correlação e motivo; a porta `AuditTrail` redige campos de segredo (token, credencial, autorização e equivalentes), omite campos de conteúdo e limita tamanho/profundidade de snapshots antes de persistir registros imutáveis.
- **Conteúdo hostil:** parsing de arquivos, webhooks e URLs ocorre em ambiente isolado com limites de tamanho, tempo e rede.
- **Supply chain:** lockfiles, SBOM/scan, assinaturas e atualização controlada são requisitos de entrega, detalhados no [processo de supply chain](supply-chain.md).

## Operação de identidade

Os papéis iniciais são leitor, contribuidor, aprovador de domínio e administrador de plataforma. Uma concessão continua limitada ao escopo que a declarou, inclusive para administrador, e define seu teto de classificação. O Portal recebe `name` e `avatar_initials` do principal autenticado para reproduzir o avatar circular e o menu de usuário do cabeçalho normativo; a interface de gerenciamento dessas concessões permanece planejada.

O modelo de identidades e autorização está definido no [ADR-010](../adr/010-identity-and-authorization.md). Pessoas usam sessão no Portal; CI, plugins e workers usam tokens opacos de curta duração, com escopos explícitos. A decisão por organização, aplicação, módulo e classificação é aplicada antes de recuperar conteúdo para busca, grafo ou IA, nunca como filtro posterior.

Antes do login por sessão, o Portal solicita `GET /auth/csrf` e envia o token
daquela sessão no cabeçalho `X-CSRF-TOKEN` para `POST /auth/session`. A rota só
entrega proteção contra falsificação de requisição; ela não autentica nem
concede permissões.

## Barreira de classificação de conteúdo

Na recepção de uma `SourceObservation`, a barreira determinística registra uma
decisão com classificação de fonte, método, confiança, revisão e data. Regras
detectam e marcam dados pessoais (`restrito`) e identificam chaves de API,
tokens Bearer, credenciais de configuração e strings de conexão. Uma detecção
de segredo força `confidencial`, redige o valor antes de persistir conteúdo,
payload ou artefato, mantém a revisão em `observed` com observação
`quarantined`, não emite evento para workers/índice e abre alerta ao responsável
com tipo e fingerprint, nunca o segredo.

Uma sobrescrita manual exige nível e motivo e é auditada como método
`manual_override`; a decisão continua vinculada à revisão. A mesma classificação
é aplicada em defesa em profundidade na projeção de busca, na filtragem da
resposta/facetas/contagens e no contexto enviado a provedores externos. O
dashboard de saúde calcula agregados apenas sobre revisões visíveis ao principal,
logo conteúdo restrito não é inferível por métricas ou dívida por quem não tem
o teto de classificação correspondente.
