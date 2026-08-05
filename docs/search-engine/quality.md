# Qualidade de busca

## Conjunto de avaliação

[`evaluation/queries.v1.json`](evaluation/queries.v1.json) é a suíte versionada
de consultas com julgamentos de relevância. Ela é inteiramente sintética e
pública: não deve receber conteúdo de clientes, código proprietário, segredos
ou dados pessoais. A versão atual cobre nome de classe, path de endpoint,
código de regra, linguagem natural e sigla. Cada julgamento associa o ID da
projeção publicada a uma nota de relevância de 0 a 3.

O corpus de CI em [`evaluation/results.v1.json`](evaluation/results.v1.json)
é um adaptador determinístico do índice e não é uma fonte de conhecimento. A
execução normal consulta o `HybridSearchIndex` configurado (Typesense); a
fixture existe para tornar a medição e a regressão repetíveis sem depender de
um serviço externo.

## Execução e métricas

Execute a avaliação real após indexar o corpus público no ambiente alvo:

```bash
cd apps/backend
php artisan search:evaluate
```

Para a execução reprodutível usada no CI:

```bash
cd apps/backend
php artisan search:evaluate \
  --results=../../docs/search-engine/evaluation/results.v1.json
```

O comando registra uma linha JSON por execução em
`storage/app/search-evaluations/history.jsonl` (ou em `--history`), com data,
configuração de embedding/RRF, resultados por query e métricas agregadas. Isso
forma uma série temporal comparável sem persistir texto de consultas fora da
suíte versionada.

As métricas são `recall@k`, MRR, nDCG@k, p50/p95 de latência, taxa de
zero-result e cobertura de citações. Cobertura de citações é a fração dos
resultados relevantes recuperados que contém `origin_uri`, revisão da fonte,
método e confiança; assim uma recuperação útil sem proveniência não recebe
crédito completo.

O baseline em [`evaluation/baseline.v1.json`](evaluation/baseline.v1.json) é
comparado pelos limiares de `config/search.php`. O job do CI é informativo na
execução e falha somente quando uma queda de qualidade, aumento de zero-result
ou aumento de p95 excede esses limiares. Ao atualizar o baseline, registre no
PR o motivo, a configuração e o resultado anterior/posterior.

## Regra de mudança de recuperação

Qualquer troca de modelo ou versão de embedding, estratégia de fusão ou ranking
exige executar `search:evaluate`, anexar o resultado ao PR e revisar o delta
contra o baseline antes do merge. Uma regressão além do limiar não pode ser
aceita sem ajustar deliberadamente o limiar ou a suíte, com justificativa de
produto e revisão humana.
