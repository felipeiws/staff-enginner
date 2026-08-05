# ADR-009: Estado `observed` para recepção de observações

Status: Accepted

## Contexto

O contrato original iniciava a curadoria em `draft`/`candidate`, mas a recepção de uma `SourceObservation` precisa registrar que a fonte e sua revisão foram recebidas antes de qualquer normalização ou decisão editorial. A tarefa de ingestão requer que a `entity_revision` seja criada nesse estado.

## Decisão

Adicionar `observed` ao vocabulário de ciclo de vida dos contratos e permitir esse valor em `entity_revisions`. A única transição inicial prevista é `observed` para `candidate` (ou `retracted` em retenção/correção). A ingestão grava método, versão do extractor, checksum, ator e revisão de origem antes de emitir `KnowledgeRevisionCreated` pela outbox.

## Alternativas

Usar `candidate` imediatamente esconderia a distinção entre recebimento e conteúdo já normalizado. Guardar o estado apenas em metadados impediria validação de contrato e consultas confiáveis. Criar uma tabela paralela duplicaria o vínculo entre revisão de fonte e revisão de entidade.

## Consequências

Consumidores precisam tratar `observed` como não publicável e não indexável. A alteração é aditiva no contrato v1; os intermediários continuam tolerantes a campos desconhecidos, mas consumidores que fechem o enum devem atualizar antes de produzir esse estado.
