# Copilot

O Copilot é consumidor do conhecimento publicado: não escreve no grafo e toda resposta é candidata, nunca autoridade. O chamador preserva as evidências, origem, revisão, método e confiança que compõem o contexto; a resposta mantém a execução, modelo e versão para auditoria.

## Linha de raciocínio auditável

Cada execução bem-sucedida persiste `result.reasoning_trace`, um registro estruturado e versionado do pipeline: interpretação determinística da pergunta, contagens por canal de recuperação, fusão, seleção e descartes com motivo, decisão de rerank, confrontos realizados entre evidências documentais e de código e restrições de regras de negócio recuperadas. Os textos curtos exibidos pelo Portal são formatados desse registro; a LLM não os escreve nem os completa. A repetição com a mesma `idempotency_key` retorna o mesmo traço que ficou associado à execução.

Confrontos só são registrados quando uma evidência documental e outra de código compartilham `entity_id`; a versão inicial registra o método `shared_entity_id_v1` e o fato de que foram comparadas, sem inferir alinhamento ou divergência sem um analisador com evidência própria. Uma fonte `Regra` limita a formulação da resposta por seu trecho e conserva URI e revisão.

## Provedor de modelo

`apps/backend/src/Modules/Copilot` define a porta `ModelProvider`. A implementação inicial `AnthropicModelProvider` usa a Messages API, o modelo fixado por padrão `claude-opus-4-1-20250805`, `max_tokens`, `temperature`, timeout e tentativas configurados. O prompt de sistema estável recebe `cache_control: { type: ephemeral }` para aproveitar o cache de prompt da Anthropic. A configuração é um snapshot em `executions.parameters`; segredos não entram nesse snapshot, no resultado ou em logs.

Antes de montar a string do prompt, `ExternalProviderContentPolicy` exige que cada classificação do contexto tenha `external_provider_allowed=true` em `classifications`. Ausência de classificação ou política permissiva bloqueia o envio por padrão.

O provedor `deterministic` é exclusivamente local ao seed e aos testes de integração. Como ele não transmite contexto para fora do processo, não representa uma exceção à política de envio externo; a classificação continua preservada na execução, mas não exige `external_provider_allowed`.

## Limites, orçamento e idempotência

`CopilotExecutionService` aplica teto de entrada, saída e total por requisição. O limite de entrada usa um caractere Unicode como limite superior conservador de token. Também calcula o uso e custo das execuções concluídas no período configurável, reservando o pior caso da solicitação antes de chamar o provedor. Após a resposta, tokens de entrada, escrita/leitura de cache e saída atualizam o custo efetivo em USD.

A `idempotency_key` única em `executions` devolve o mesmo resultado para uma repetição já concluída. O adaptador envia a chave também como `X-Atlas-Idempotency-Key`; a proteção autoritativa permanece local porque não se pressupõe semântica de idempotência do provedor. Falhas transitórias de conexão, `429` e `5xx` recebem retry limitado; qualquer falha persistida não inclui prompt, conteúdo ou segredo.

## Configuração e verificação

As variáveis `COPILOT_*` e `ANTHROPIC_*` estão em `apps/backend/.env.example`. O teste de integração real é opcional e só roda com `ANTHROPIC_CONTRACT_TESTS=1` e `ANTHROPIC_API_KEY`; ele pode gerar custo. Os testes usuais usam `FakeModelProvider`.

## Abstenção e lacunas rastreadas

O Copiloto só chama o provedor quando a quantidade de fontes citáveis e a
confiança média atingem os limiares configuráveis de lacunas. Caso contrário —
inclusive se a resposta do modelo não tiver as citações obrigatórias — responde
`422 copilot.insufficient_evidence` e registra a pergunta como uma lacuna
proveniente de `copilot.abstention`. A pergunta não é enviada ao provedor para
resolver esse agrupamento. Consulte a [API de lacunas](../development/design-documents/knowledge-gaps-api.md).
