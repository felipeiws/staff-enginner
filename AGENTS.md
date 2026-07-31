# Guia para agentes de IA

Este repositório é uma fundação arquitetural. Não assuma que documentos de intenção autorizam implementar código: só implemente quando a solicitação pedir explicitamente e delimitar o módulo.

## Modelo mental

O núcleo é o Knowledge Engine. Ele recebe observações de fontes versionadas, mantém proveniência e evidencia relações no Knowledge Graph. Busca, IA e plugins são consumidores/produtores desacoplados por contratos, não atalhos para acessar o banco diretamente.

Antes de alterar algo, leia `docs/architecture/overview.md`, o documento do módulo relevante e os ADRs relacionados. Preserve decisões existentes; se mudar uma decisão transversal, proponha um ADR, inclusive alternativas e consequências.

## Convenções de trabalho

- Mantenha dependências orientadas para o centro: portal/plugins/workers dependem de contratos; contratos não dependem deles.
- Não trate conteúdo derivado como fonte de verdade. Guarde origem, revisão, versão, confiança e data de processamento.
- Não introduza IA como autoridade. Saídas devem indicar evidências, modelo/configuração e ter fluxo de aprovação quando publicáveis.
- Evite acoplamento entre plugins; um plugin comunica-se por contratos e eventos documentados.
- Documente decisões no mesmo PR: objetivo, porquê, impacto, riscos, métricas e rollback quando aplicável.

## Ao criar módulos ou plugins

Parta dos contratos em `packages/contracts` (quando existirem) e da especificação em `docs/plugin-system`. Não criar migrações, endpoints ou UI sem um Design Document aprovado. Todo plugin precisa declarar capacidades, permissões, versões compatíveis, idempotência, limites e observabilidade.

## Documentação e ADRs

Use Markdown, links relativos e Mermaid para fluxos. ADRs são imutáveis após aceitos: crie outro que os substitua. Atualize o índice de ADRs e o documento de arquitetura que for afetado. Não faça afirmações de implementação ainda inexistente; use “planejado”, “alvo” ou “contrato proposto”.

## Verificação

Para trabalho documental, valide links internos, árvore de arquivos e renderização básica de Mermaid quando houver ferramenta disponível. Não invente resultados de testes. Mudanças de arquitetura exigem revisão humana.
