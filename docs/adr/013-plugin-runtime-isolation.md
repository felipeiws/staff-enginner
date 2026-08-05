# ADR-013: Execução de plugins somente em runtime isolado

Status: Accepted

## Contexto

Plugins processam código, documentos, webhooks e endpoints externos, portanto carregam dependências e conteúdo potencialmente hostis. O registro e o contrato de plugins já existem no backend, mas uma chamada no processo Laravel herdaria o mesmo acesso a memória, rede e configuração do núcleo.

## Alternativas descartadas

- Carregar plugins como pacotes PHP no processo do backend reduz a infraestrutura inicial, mas não contém falhas nem impede acesso acidental a recursos do núcleo.
- Permitir execução local de desenvolvimento como fallback torna a configuração insegura fácil de promover e invalida a fronteira de confiança.
- Conceder acesso direto a PostgreSQL, Redis ou Typesense torna cada plugin um consumidor de schema interno e viola os ADRs 003 e 005.
- Abrir rede por padrão simplifica conectores, mas amplia exfiltração, SSRF e o raio de comprometimento.

## Decisão

O `PluginHost` só delega para plugins habilitados e o resolvedor cria uma política imutável a partir do manifesto aprovado. A execução é encaminhada a um control plane de runtime isolado; sem executor isolado configurado, falha fechado. Não existe fallback de execução no processo Laravel.

Cada execução recebe somente operação, escopo, correlação, deadline, chave de idempotência e payload validado. A política limita CPU, memória, concorrência, prazo e tamanho de payload. O runtime usa filesystem efêmero, nenhum volume/socket do backend e segredos de curta duração resolvidos de referências `secret://`. A rede começa negada; quando habilitada, exige allowlist explícita. Resultados retornam exclusivamente observações e evidências candidatas pelos contratos públicos.

## Consequências

O ambiente operacional precisa fornecer control plane, sandbox, distribuição de segredos, telemetria e políticas de rede antes de habilitar execução produtiva. Isso aumenta a latência e a complexidade de operação, porém contém falhas por plugin, preserva a direção das dependências e mantém o núcleo disponível quando um plugin falha.

O manifesto, as transições e as métricas por `plugin_id` permanecem auditáveis. Instalações administrativas continuam possíveis; marketplace aberto requer verificação de proveniência assinada do artefato antes da habilitação.

## Critérios de revisão

Revisar ao introduzir novo tipo de runtime, permissões de filesystem/rede, marketplace, execução multi-tenant ou evidência de que os limites impedem capacidade legítima. Qualquer exceção de egress, mount ou acesso a segredo exige análise de ameaça, prazo de expiração e plano de rollback.
