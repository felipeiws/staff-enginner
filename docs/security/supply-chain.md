# Supply chain e entrega endurecida

## Objetivo e escopo

Este processo reduz o risco de dependências, artefatos e credenciais alterarem
a entrega da Atlas sem rastreabilidade. Ele concretiza o requisito de supply
chain da [arquitetura de segurança](architecture.md) e usa Apache-2.0 como
referência de compatibilidade, conforme o [ADR-008](../adr/008-open-source-license.md).
Enquanto o ADR-008 estiver proposto, esta é uma política de engenharia para
dependências; não altera a licença jurídica do repositório.

## Controles obrigatórios

| Controle               | Aplicação                                                                                                                                                                                                                 | Bloqueio                                                                                                                        |
| ---------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------- |
| Resolução reprodutível | `pnpm-lock.yaml`, os dois `composer.lock` e `apps/worker/uv.lock` são obrigatórios. CI executa `pnpm install --frozen-lockfile`, `composer validate --strict`/`install --dry-run` e `uv lock --check`/`uv sync --locked`. | Ausência ou divergência de lockfile bloqueia. `requirements.txt` é apenas compatibilidade local e não é entrada de CI.          |
| Segredos               | Gitleaks roda no hook `pre-commit` e em todo PR/push, com valores redigidos.                                                                                                                                              | Qualquer segredo fora da allowlist versionada bloqueia.                                                                         |
| Vulnerabilidades       | Trivy varre dependências e cada imagem construída.                                                                                                                                                                        | CVE de severidade **CRITICAL**, inclusive não corrigida, bloqueia. Alto e médio geram acompanhamento, mas não bloqueiam por si. |
| SBOM                   | O Portal gera SBOM CycloneDX do `dist`; Node, PHP e Python geram SBOMs CycloneDX das dependências; cada imagem gera seu próprio SBOM CycloneDX.                                                                           | Falha na geração, na publicação do artefato de CI ou no formato CycloneDX bloqueia.                                             |
| Licenças               | A política versionada aceita `Apache-2.0`, MIT, BSD, ISC, MPL-2.0 e as demais licenças permissivas explicitadas em `scripts/check-sbom-licenses.mjs`.                                                                     | Licença ausente, desconhecida ou fora da allowlist bloqueia.                                                                    |
| Imagens                | Dockerfiles/Containerfiles encontrados são construídos e varridos antes de release. Uma tag `v*` publica no GHCR e assina cada digest com Cosign/OIDC.                                                                    | Imagem crítica, push ou assinatura falhos bloqueiam a entrega.                                                                  |
| Atualizações           | Dependabot abre PRs semanais e agrupa apenas minor/patch de dependências de desenvolvimento e GitHub Actions.                                                                                                             | Atualizações major ou de produção permanecem isoladas para revisão humana.                                                      |

Os workflows relevantes são [`ci.yml`](../../.github/workflows/ci.yml) e
[`container-release.yml`](../../.github/workflows/container-release.yml). O
segundo não publica nada enquanto não houver Dockerfile/Containerfile; ao
adicionar um, o contexto de build deve ser compatível com `docker build -f
<arquivo> .`.

## Uso local

Instale o hook uma vez com `pre-commit install`; ele usa
`.pre-commit-config.yaml`. Para executar os guardrails disponíveis sem criar
um commit:

```bash
pre-commit run --all-files
node scripts/verify-lockfiles.mjs
uv lock --check --project apps/worker
```

O CI é a autoridade: os resultados e SBOMs são anexados como artefatos da
execução. O hook local é uma defesa antecipada e não substitui o bloqueio no
servidor.

## Exceções com prazo

Não se silencia uma falha alterando o limiar, removendo o scanner ou inserindo
um segredo na allowlist. Uma exceção temporária deve ser registrada no PR e no
rastreado de segurança com:

1. componente, versão, CVE ou licença e evidência do scanner/SBOM;
2. impacto, motivo técnico e controles compensatórios;
3. responsável nominal (papel `administrador_de_plataforma`) e aprovador de
   segurança;
4. prazo de expiração, de no máximo 30 dias, e issue de remoção;
5. revisão semanal até a remoção.

A exceção é implementada na configuração versionada e limitada ao componente
afetado. Ao expirar, o CI volta a bloquear automaticamente. Para segredos,
não há exceção: o valor deve ser revogado/rotacionado e removido do histórico
quando o procedimento de resposta autorizar.

## Rollback e métricas

Um rollback de aplicação continua usando uma imagem já assinada e seu SBOM;
nunca se recompila uma imagem antiga para contornar o processo. A operação
acompanha quantidade de bloqueios críticos, idade das exceções, tempo até
correção e porcentagem de imagens publicadas com SBOM e assinatura verificáveis.
