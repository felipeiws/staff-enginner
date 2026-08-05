# Governança do conhecimento

Conhecimento publicado deve ter proprietário de domínio, escopo e política de revisão. Fontes podem ser confiáveis, mas ainda assim gerar candidatos quando parsing ou inferência introduzir ambiguidade.

Políticas devem ser declarativas e avaliáveis: quais fontes/tipos podem publicar automaticamente, quais classificações exigem revisão, quem pode aprovar, por quanto tempo conteúdo permanece válido e como uma retratação é comunicada. Auditoria guarda decisão e justificativa, não apenas o estado final.

A saúde exibida no Portal é uma projeção calculada das revisões e aprovações, descrita em [Saúde do conhecimento](knowledge-health.md); não é um atributo editável da aplicação ou do módulo.

Perguntas sem evidência suficiente são lacunas de conhecimento, não respostas
inferidas. Elas agrupam ocorrências semanticamente equivalentes por escopo,
retêm pergunta, origem, método, confiança e período e podem ser atribuídas a um
responsável. A atribuição abre uma tarefa de documentação auditável; não publica
conteúdo nem concede autoridade à IA. Ver [API de lacunas](../development/design-documents/knowledge-gaps-api.md).

## DSL de políticas (v1)

O contrato [`governance-policy.json`](../../packages/contracts/schemas/v1/governance-policy.json) valida um documento YAML antes da carga. Cada versão é inserida, nunca sobrescrita, em `policies`; `policy_key`, `version`, checksum, origem, revisão, método, confiança e autor preservam a proveniência da regra.

```yaml
schema_version: 1.0.0
policies:
  - policy_key: ci-baixo-impacto
    version: 1
    name: CI de baixo impacto
    priority: 100
    applies_to:
      source_types: [git]
      entity_types: [Documento]
      classifications: [interno]
      derivation_methods: [parser]
      confidence: { min: 0.85 }
      scope: { application: [atlas] }
      owner: { roles: [technical] }
      impact_levels: [baixo]
    outcome:
      action: review
      approver_roles: [technical]
      auto_approve_after_hours: 48
      validity_days: 90
      freshness_warning_days: 7
      retraction_behavior: retain_audit_history
```

O motor recebe fonte, tipo, classificação, método, confiança, escopo, dono e impacto. Ele retorna `publish`, `review` ou `block`, aprovadores, validade/frescor e uma explicação da regra e condições que casaram. Sem política aplicável, bloqueia de forma segura. A precedência é determinística: prioridade decrescente, especificidade decrescente, `policy_key` crescente e versão decrescente. Uma regra `review` de impacto `baixo` com `auto_approve_after_hours: 48` vira `publish` somente depois das 48 horas; antes disso permanece candidata para revisão humana. `validity_days` produz `valid_until` e `freshness` (`atual`, `expira_em_breve` ou `expirado`) para alimentar o selo de frescor do Portal.

## Persistência inicial

As tabelas da família de governança mantêm essas decisões separadas do grafo publicado. Classificações declaram permissões de exposição para busca, exibição e provedores externos. Políticas são avaliadas por prioridade e vigência; a regra mais prioritária aplicável determina publicação automática, revisão requerida, papéis aprovadores, validade e tratamento de retratação.

Cada escopo ou módulo deve ter registros de responsabilidade `technical` e `business` quando ambos forem aplicáveis. Aprovações são vinculadas a uma proposta e a uma revisão de entidade ou assertion, e uma decisão só é válida com revisor humano, justificativa e data. Registros de `audit_log` são imutáveis no banco: correções exigem um novo evento de auditoria, nunca a edição do anterior. Aprovações e versões de política usam o contrato transversal `AuditTrail`; os snapshots são sanitizados antes da gravação.

## Revisão humana e Pull Request

Uma proposta pendente retém a entidade/revisão alvo, origem (commit, CI, IA ou humano), análise de IA e sua confiança, texto atual e proposto, entidades impactadas, método, revisão de origem e data de processamento. O revisor humano pode editar o texto (com snapshot anterior e posterior na auditoria), pedir ajuste à IA, aprovar ou rejeitar; a justificativa é obrigatória em toda decisão e compõe a mensagem de commit.

Ao aprovar, o caso de uso abre o Pull Request pelo conector configurado antes de publicar a revisão via `KnowledgeLifecycleService`. Se o conector falhar, a proposta continua pendente e a revisão não é publicada nem decidida. Após a referência externa ser obtida, a decisão, a publicação, a auditoria e as propostas de revisão automática para entidades dependentes são gravadas na transação local. A abertura de PR é uma integração de conector: o módulo de governança não acessa repositórios, credenciais ou tabelas de plugins.

Uma rejeição retrata a revisão que estava em `review` e mantém tanto a proposta quanto a decisão e sua auditoria; não há exclusão física. Entidades dependentes publicadas são marcadas por uma nova proposta pendente de revisão automática, mantendo seu conteúdo publicado até uma decisão humana posterior.

## API de Aprovações

A [API de aprovações v1](../development/design-documents/approval-api.md) implementa a consulta da fila e os comandos usados pela tela de Aprovações. A autorização de leitura e de aprovação é resolvida por escopo e classificação antes de expor ou alterar uma proposta; os comandos exigem `Idempotency-Key`.

Propostas assistidas por IA de FAQ ou atualização de Processo entram sempre em `review`, com citações por URI e revisão, confiança e execução do modelo. A IA não cria Processo: só pode atualizar um Processo previamente selecionado por humano.
