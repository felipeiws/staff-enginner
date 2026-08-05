# Design Documents

Use um Design Document antes de mudanças que cruzem módulos, afetem dados, adicionem dependências/integrações, modifiquem segurança, criem custos recorrentes ou tenham rollback não trivial.

## Template

```markdown
# Título
## Problema e objetivo
## Não objetivos
## Contexto e evidências
## Proposta e contratos
## Alternativas consideradas
## Dados, segurança e privacidade
## Operação: métricas, alertas e falhas
## Compatibilidade, migração e rollback
## Riscos e questões em aberto
## Decisões/ADRs relacionados
```

Desenhos descrevem uma proposta em evolução; ADRs registram a decisão resultante. Evite transformar o documento em implementação disfarçada: foque fronteiras, invariantes e critérios de aceitação arquiteturais.

## Documentos ativos

- [Ingestão de artefatos visuais](design-documents/visual-artifact-ingestion.md)
- [API de lacunas de conhecimento](design-documents/knowledge-gaps-api.md)
- [API de saúde do conhecimento](design-documents/knowledge-health-api.md)
- [API de propostas assistidas de conhecimento](design-documents/knowledge-candidate-api.md)
