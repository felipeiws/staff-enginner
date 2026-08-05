import type { LifecycleState, Predicate } from './generated/assertion.js';

/** Vocabulário fechado de relações permitido por Assertion v1. */
export enum GraphPredicate {
  Contains = 'contains',
  BelongsTo = 'belongs_to',
  Implements = 'implements',
  Calls = 'calls',
  Exposes = 'exposes',
  Consumes = 'consumes',
  Produces = 'produces',
  Reads = 'reads',
  Writes = 'writes',
  Governs = 'governs',
  Documents = 'documents',
  DependsOn = 'depends_on',
  Supersedes = 'supersedes',
  OwnedBy = 'owned_by',
  RelatedTo = 'related_to',
  Contradicts = 'contradicts',
}
export const graphPredicates = Object.values(GraphPredicate) as readonly Predicate[];

/** Estados de curadoria definidos pelos contratos do Knowledge Engine. */
export enum KnowledgeLifecycleState {
  Draft = 'draft',
  Candidate = 'candidate',
  Review = 'review',
  Published = 'published',
  Superseded = 'superseded',
  Retracted = 'retracted',
}
export const knowledgeLifecycleStates = Object.values(
  KnowledgeLifecycleState,
) as readonly LifecycleState[];

export enum EntityFamily {
  Context = 'Contexto',
  Software = 'Software',
  Knowledge = 'Conhecimento',
  Provenance = 'Proveniência',
  Governance = 'Governança',
}

/** Tipos iniciais do modelo conceitual do grafo; plugins podem acrescentar tipos. */
export enum GraphEntityType {
  Organization = 'Organização',
  Space = 'Espaço',
  Application = 'Aplicação',
  Module = 'Módulo',
  Process = 'Processo',
  Owner = 'Responsável',
  Integration = 'Integração',
  Repository = 'Repositório',
  Endpoint = 'Endpoint',
  Api = 'API',
  Controller = 'Controller',
  Service = 'Service',
  Action = 'Action',
  Job = 'Job',
  Event = 'Evento',
  Table = 'Tabela',
  Migration = 'Migration',
  Screen = 'Tela',
  Rule = 'Regra',
  Document = 'Documento',
  Adr = 'ADR',
  Faq = 'FAQ',
  Comment = 'Comentário',
  Issue = 'Issue',
  Release = 'Release',
  Video = 'Vídeo',
  Print = 'Print',
  Diagram = 'Diagrama',
  Source = 'Fonte',
  Artifact = 'Artefato',
  Revision = 'Revisão',
  Chunk = 'Chunk',
  Evidence = 'Evidência',
  Observation = 'Observação',
  Execution = 'Execução',
  Classification = 'Classificação',
  Policy = 'Política',
  Approval = 'Aprovação',
  Audit = 'Auditoria',
  Retention = 'Retenção',
}

export const entityTypesByFamily: Readonly<Record<EntityFamily, readonly GraphEntityType[]>> = {
  [EntityFamily.Context]: [
    GraphEntityType.Organization,
    GraphEntityType.Space,
    GraphEntityType.Application,
    GraphEntityType.Module,
    GraphEntityType.Process,
    GraphEntityType.Owner,
    GraphEntityType.Integration,
  ],
  [EntityFamily.Software]: [
    GraphEntityType.Repository,
    GraphEntityType.Endpoint,
    GraphEntityType.Api,
    GraphEntityType.Controller,
    GraphEntityType.Service,
    GraphEntityType.Action,
    GraphEntityType.Job,
    GraphEntityType.Event,
    GraphEntityType.Table,
    GraphEntityType.Migration,
    GraphEntityType.Screen,
    GraphEntityType.Rule,
  ],
  [EntityFamily.Knowledge]: [
    GraphEntityType.Document,
    GraphEntityType.Adr,
    GraphEntityType.Faq,
    GraphEntityType.Comment,
    GraphEntityType.Issue,
    GraphEntityType.Release,
    GraphEntityType.Video,
    GraphEntityType.Print,
    GraphEntityType.Diagram,
  ],
  [EntityFamily.Provenance]: [
    GraphEntityType.Source,
    GraphEntityType.Artifact,
    GraphEntityType.Revision,
    GraphEntityType.Chunk,
    GraphEntityType.Evidence,
    GraphEntityType.Observation,
    GraphEntityType.Execution,
  ],
  [EntityFamily.Governance]: [
    GraphEntityType.Classification,
    GraphEntityType.Policy,
    GraphEntityType.Approval,
    GraphEntityType.Audit,
    GraphEntityType.Retention,
  ],
};

export type DesignTokenColor =
  | 'var(--violet)'
  | 'var(--warn)'
  | 'var(--info)'
  | 'var(--acc)'
  | 'var(--pink)'
  | 'var(--sky)'
  | 'var(--danger)'
  | 'var(--tx2)';

/** Cores normativas da legenda do grafo e dos badges de resultado do protótipo. */
export const entityTypeTokenColor = {
  [GraphEntityType.Process]: 'var(--violet)',
  [GraphEntityType.Rule]: 'var(--warn)',
  [GraphEntityType.Screen]: 'var(--info)',
  [GraphEntityType.Api]: 'var(--acc)',
  [GraphEntityType.Endpoint]: 'var(--acc)',
  [GraphEntityType.Event]: 'var(--pink)',
  [GraphEntityType.Table]: 'var(--sky)',
  [GraphEntityType.Faq]: 'var(--sky)',
  [GraphEntityType.Issue]: 'var(--danger)',
  [GraphEntityType.Document]: 'var(--tx2)',
} as const satisfies Readonly<Partial<Record<GraphEntityType, DesignTokenColor>>>;
