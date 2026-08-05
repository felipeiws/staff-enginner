# Saúde do conhecimento

## Fórmula v1

O score é uma projeção calculada, nunca um campo editável. Para uma aplicação ou módulo, considera as entidades ativas do escopo: a revisão mais recente de cada entidade que não esteja `retracted`. Entidades de contexto (`Organização`, `Aplicação` e `Módulo`) não entram no denominador.

```
saúde = arredondar(0,50 × cobertura + 0,25 × frescor + 0,15 × confiança + 0,10 × aprovações)
```

- **cobertura**: percentual de entidades elegíveis cuja revisão atual está `published`;
- **frescor**: percentual das revisões publicadas com `published_at` nos últimos 90 dias;
- **confiança**: média da confiança das revisões publicadas, convertida para 0–100;
- **aprovações**: `100 × (1 - pendências / propostas)`. Propostas são aprovações do escopo; sem proposta, vale 100. Uma pendência é uma aprovação sem `decision`.

Sem entidade elegível, o score é 0. Cada componente é limitado ao intervalo 0–100 antes da ponderação. A fonte de cada componente são as revisões, aprovações e fontes publicadas do Knowledge Engine; a resposta da API declara o método `knowledge_health_v1` e a confiança agregada média. Isso impede que um valor manual se torne autoridade e torna a projeção reproduzível.

## Rótulos

| Score | Status |
| --- | --- |
| 75–100 | Saudável |
| 50–74 | Atenção |
| 0–49 | Cobertura baixa |

Mudanças em pesos, janela de frescor ou rótulos criam uma nova versão documentada da fórmula e preservam a versão usada nas respostas históricas quando houver retenção delas.

## Dashboard e série de 30 dias

`GET /api/v1/knowledge-health?scope={uuid}&period=30d` calcula a projeção da
tela **Saúde do conhecimento**. `scope` é o UUID opaco da entidade de contexto
`Organização`, `Aplicação` ou `Módulo`; a API resolve seu escopo versionado e
autoriza `knowledge.read` antes de devolver o resultado.

As quatro métricas são calculadas, não persistidas:

- **Cobertura documental** é a cobertura da fórmula v1, em percentual.
- **Índice de confiança da IA** é a média (0–1) das revisões elegíveis
  `published`. É evidência agregada, e não uma decisão da IA.
- **Perguntas sem resposta** é a soma das ocorrências de `knowledge_gaps` cujo
  último evento está na janela; a pergunta continua uma lacuna até curadoria
  humana, nunca uma resposta inferida.
- **Tempo médio de aprovação** é a média, em horas, entre `proposed_at` e
  `decided_at` das aprovações decididas na janela.

Cada sparkline tem nove pontos igualmente espaçados no período. Em cada ponto,
o grafo considera somente revisões existentes até aquele instante; lacunas e
aprovações são filtradas pela auditoria/timestamps do mesmo corte. A variação é
o valor atual menos o primeiro ponto. Cobertura e confiança melhoram quando
crescem; perguntas e tempo melhoram quando diminuem. Essa regra define o token
de tendência `var(--acc)`, `var(--warn)` ou `var(--danger)` devolvido pela API.

## Dívida e insight

As barras usam valor absoluto, total de referência e percentual (`valor / total
× 100`, arredondado):

- **Documentações desatualizadas**: `Documento` publicado há mais de 90 dias,
  sobre todos os documentos publicados.
- **Regras sem responsável**: `Regra` publicada sem `entities.owner_id` válido
  e sem registro ativo de responsabilidade `owners` para a entidade, sobre
  todas as regras publicadas.
- **Telas sem print**: `Tela` publicada sem `visual_artifact` ou print desktop
  na revisão, sobre todas as telas publicadas.
- **Documentos nunca acessados**: documento publicado sem evento de auditoria
  `knowledge.accessed` ou `knowledge.read` para sua entidade, sobre todos os
  documentos publicados.

O insight correlaciona módulos abaixo de 50% de cobertura com entidades `Issue`
publicadas de `attributes.kind = support_ticket`. Ele devolve o numerador, o
total e a participação; se não existir ticket com proveniência no escopo, a API
declara explicitamente que não há correlação em vez de inventar uma conclusão.

O resultado identifica `knowledge_health_dashboard_v1`, a confiança média
agregada, `knowledge_graph + audit_log` e o instante de processamento. Ele é
cacheado por escopo por até cinco minutos. O consumidor idempotente
`governance.knowledge-health-cache` invalida a chave quando recebe um evento de
domínio com o mesmo escopo, mantendo a projeção derivada fora da fonte de
verdade.
