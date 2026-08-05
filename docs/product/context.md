# Plataforma Open Source de Engenharia do Conhecimento para Software

> Documento de contexto e decisões arquiteturais do projeto.
>
> Status: **V0.1**
>
> Este documento consolida todas as decisões tomadas até o momento e serve como referência para arquitetos, desenvolvedores, designers e agentes de IA.

---

# Visão

Este projeto **não é um portal de documentação**.

Também não é uma Wiki, um Confluence, um Docusaurus ou apenas um Chat com IA.

Estamos construindo uma **Plataforma Open Source de Engenharia do Conhecimento para Software**, cujo objetivo é transformar todo o conhecimento existente sobre uma aplicação em um ativo estruturado, pesquisável e reutilizável.

A plataforma deve combinar:

- velocidade de busca semelhante ao Google;
- capacidade de compreensão semelhante ao ChatGPT;
- confiabilidade baseada em evidências;
- conhecimento estruturado através de um Knowledge Graph.

---

# Problema

Hoje o conhecimento de uma aplicação está espalhado em diversos lugares:

- código-fonte;
- Markdown;
- Confluence;
- Notion;
- Swagger/OpenAPI;
- Banco de dados;
- Jira;
- GitHub;
- GitLab;
- Azure DevOps;
- vídeos;
- prints;
- documentação informal;
- conhecimento das pessoas.

Nenhum sistema consegue responder perguntas utilizando todo esse contexto.

Na prática, boa parte do conhecimento permanece somente na cabeça dos desenvolvedores.

Quando uma pessoa deixa a empresa, esse conhecimento também vai embora.

---

# Missão

Transformar conhecimento em um ativo versionado.

Toda informação deve ser:

- pesquisável;
- relacionada;
- versionada;
- auditável;
- reutilizável;
- consumível por pessoas;
- consumível por agentes de IA.

---

# Objetivo

Permitir que qualquer pessoa consiga perguntar:

> "Como funciona o cancelamento de pedido?"

E receber uma resposta baseada em evidências, contendo:

- explicação;
- processos;
- regras de negócio;
- telas;
- endpoints;
- controllers;
- services;
- banco de dados;
- integrações;
- documentação;
- responsáveis;
- histórico de alterações;
- commits;
- fontes utilizadas.

---

# Filosofia

A IA não deve inventar respostas.

Ela deve responder utilizando conhecimento indexado.

A plataforma deve explicar:

- por que respondeu;
- quais evidências utilizou;
- qual o nível de confiança;
- quais documentos deram origem à resposta.

---

# Conceito

Google encontra documentos.

Esta plataforma encontra conhecimento.

---

# Conhecimento como ativo

A plataforma trata conhecimento da mesma forma que uma empresa trata:

- código;
- banco de dados;
- infraestrutura.

Conhecimento passa a ser um ativo estruturado.

---

# Knowledge Graph

O centro da plataforma será um Knowledge Graph.

Todo conteúdo indexado gera entidades e relacionamentos.

O usuário navega por conhecimento e não por arquivos.

---

# Entidades principais

As entidades inicialmente previstas são:

- Organização
- Aplicação
- Módulo
- Processo
- Tela
- Documento
- FAQ
- Regra de Negócio
- Endpoint
- Controller
- Service
- Action
- Job
- Evento
- Migration
- Tabela
- Integração
- Issue
- Release
- ADR
- Comentário
- Responsável
- Vídeo
- Print

Novas entidades poderão ser adicionadas por plugins.

---

# Relacionamentos

Toda entidade pode se relacionar com outras.

Exemplo:

Processo

↓

Tela

↓

Endpoint

↓

Controller

↓

Service

↓

Regra

↓

Tabela

↓

Integração

↓

Documentação

↓

FAQ

↓

Vídeo

↓

Commits

↓

Responsáveis

---

# Fonte da verdade

Markdown continua sendo a principal fonte de conhecimento humano.

O código-fonte continua sendo a principal fonte técnica.

Nenhum deles substitui o outro.

Ambos alimentam o Knowledge Graph.

---

# Indexação

O sistema deverá indexar automaticamente informações provenientes de:

- Código
- Markdown
- Git
- OpenAPI
- Banco de Dados
- Storybook
- Playwright
- Mermaid
- Draw.io
- PDFs
- Vídeos
- Prints
- Outros plugins

---

# Arquitetura conceitual

```
Usuário

↓

Portal

↓

API

↓

Knowledge Engine

↓

Knowledge Graph

↓

Search Engine

↓

IA
```

---

# Componentes

## Portal

Responsável pela experiência do usuário.

Não contém inteligência.

É apenas um cliente da plataforma.

---

## API

Responsável por:

- autenticação;
- autorização;
- permissões;
- workflows;
- integrações;
- gestão das aplicações.

---

## Knowledge Engine

É o cérebro da plataforma.

Responsável por:

- receber dados dos indexadores;
- normalizar entidades;
- criar relacionamentos;
- enriquecer conhecimento;
- preparar contexto para IA.

---

## Search Engine

Responsável exclusivamente pela recuperação de informação.

Funções:

- busca lexical;
- busca semântica;
- filtros;
- ranking;
- reranking;
- recuperação híbrida.

---

## IA

A IA não pesquisa diretamente.

Ela recebe evidências produzidas pelo Search Engine.

Seu trabalho é sintetizar e explicar.

Nunca inventar.

---

# Arquitetura de busca

A busca será híbrida.

Composta por:

- Busca Lexical
- Busca Semântica
- Knowledge Graph
- Reranking
- IA

Fluxo:

Pergunta

↓

Entendimento da intenção

↓

Busca Lexical

↓

Busca Semântica

↓

Knowledge Graph

↓

Reranking

↓

LLM

↓

Resposta

---

# Plataforma Open Source

O projeto nasce Open Source.

Objetivos:

- comunidade;
- plugins;
- transparência;
- extensibilidade;
- auto-hospedagem.

A arquitetura deve ser SaaS Ready, mas inicialmente focada em instalações self-hosted.

---

# Sistema de Plugins

Toda tecnologia deve ser suportada através de plugins.

Exemplos:

- Laravel
- React
- Angular
- Vue
- Node
- Python
- Java
- .NET
- PostgreSQL
- MySQL
- GitHub
- GitLab
- Azure DevOps
- Jira
- Markdown
- OpenAPI
- Storybook
- Playwright
- Mermaid
- Draw.io

Cada plugin gera entidades padronizadas para o Knowledge Engine.

---

# Organização da documentação

Cada aplicação possuirá seu próprio repositório de documentação.

A plataforma indexa todos os repositórios.

Modelo adotado:

Arquitetura híbrida.

---

# Navegação

Existem duas formas principais.

## Técnica

- Arquitetura
- APIs
- Banco
- Código
- Eventos
- Filas
- Integrações

## Funcional

- Processos
- Regras
- Fluxos
- FAQs
- Troubleshooting
- Telas

---

# Workflow de documentação

Quando o código muda:

CI

↓

Indexadores

↓

Knowledge Engine

↓

IA gera sugestões

↓

Usuário revisa

↓

Aprovação

↓

Commit

↓

Reindexação

---

# Workflow manual

Usuário

↓

Editar documentação

↓

Justificar alteração

↓

Aprovação

↓

Commit

↓

Reindexação

---

# Governança

Cada módulo possui responsáveis.

Tipos:

- Técnico
- Negócio

Mudanças podem exigir múltiplas aprovações dependendo da criticidade.

---

# Processos

Processos são criados exclusivamente por humanos.

A IA apenas:

- relaciona;
- enriquece;
- resume;
- sugere conexões.

Nunca cria processos automaticamente.

---

# Prints e vídeos

Prints e vídeos são entidades do sistema.

Não são simples anexos.

Cada tela pode possuir:

- desktop;
- mobile;
- componentes;
- vídeos;
- histórico;
- versões;
- comentários;
- relacionamentos.

---

# Knowledge First

O usuário nunca precisa navegar por arquivos.

Ele navega pelo conhecimento.

---

# Público

Prioridade inicial:

1. Desenvolvedores
2. Suporte
3. QA
4. Produto
5. Arquitetura
6. Agentes de IA

---

# Stack técnica prevista

Backend

- Laravel 13

Workers

- Python

Banco principal

- PostgreSQL

Busca lexical

- Typesense (inicialmente)

Busca vetorial

- PostgreSQL + pgvector

Fila

- Redis

Containers

- Docker Compose

CI

- GitHub Actions

---

# Banco de dados

PostgreSQL será a fonte da verdade.

Inicialmente também armazenará:

- entidades;
- relacionamentos;
- embeddings;
- histórico de indexação.

O uso de Neo4j será avaliado futuramente caso consultas altamente relacionais justifiquem.

---

# Organização do projeto

Arquitetura em monorepo.

Estrutura prevista:

```
apps/
packages/
plugins/
docs/
docker/
scripts/
.github/
```

---

# Objetivos da V1

- Plataforma Open Source.
- Base arquitetural sólida.
- Sistema de plugins.
- Knowledge Engine.
- Busca híbrida.
- Knowledge Graph.
- Portal Web.
- Chat IA baseado em evidências.
- Workflow de documentação.
- Aprovação de alterações.
- Dashboard de conhecimento.

---

# O que NÃO queremos ser

- Apenas uma Wiki.
- Apenas um ChatGPT.
- Apenas um buscador.
- Apenas um sistema de documentação.

---

# Definição do produto

A plataforma será uma infraestrutura de conhecimento para engenharia de software.

Ela permitirá que pessoas e agentes de IA consultem qualquer aspecto de uma aplicação utilizando conhecimento estruturado, evidências verificáveis e relacionamentos entre todas as partes do sistema.

O objetivo final é tornar o conhecimento técnico tão importante e estruturado quanto o código-fonte da aplicação.
