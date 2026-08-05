import type { KnowledgeEntityV1 } from '@atlas/contracts';

export interface Provenance {
  origin?: string;
  origin_uri?: string;
  revision: string;
  method: string;
  confidence: number;
  processed_at: string;
}

export interface Organization {
  id: string;
  name: string;
  indexed_entities_count: number;
  applications_count: number;
  provenance: Provenance;
}
export interface KnowledgeHealthSummary {
  score: number;
  status: string;
}
export interface Application {
  id: string;
  name: string;
  modules_count: number;
  screens_count: number;
  knowledge_health: KnowledgeHealthSummary;
  provenance: Provenance;
  description?: string;
  documentation_coverage?: number;
  pending_approvals?: number;
}
export interface ApplicationTreeGroup {
  key: string;
  name: string;
  count: number;
  breakdown: Record<string, number>;
  provenance: Provenance;
}
export interface ApplicationTree {
  nav: 'technical' | 'functional';
  groups: readonly ApplicationTreeGroup[];
}
export interface ApplicationModule {
  id: string;
  name: string;
  processes_count: number;
  apis_count: number;
  knowledge_health: KnowledgeHealthSummary;
  provenance: Provenance;
}
export interface SearchParams {
  q: string;
  organization: string;
  application?: string;
  module?: string;
  entity_type?: readonly string[];
  freshness?: readonly string[];
  owner?: readonly string[];
  page_size?: number;
  embedding?: readonly number[];
}
export interface SearchResponse {
  results: readonly SearchResult[];
  type_counts: Record<string, number>;
  facets: SearchFacets;
  provenance?: Provenance;
}
export interface SearchFacetOption {
  value: string;
  count: number;
}
export interface SearchFacets {
  application?: readonly SearchFacetOption[];
  freshness?: readonly SearchFacetOption[];
  owner?: readonly SearchFacetOption[];
}
export interface SearchResult {
  id: string;
  entity_id?: string;
  title: string;
  type: { name: string; color: string };
  path?: string;
  freshness?: string;
  excerpt?: { pre: string; hit: string; post: string };
  metadata?: string;
  related_entities?: readonly string[];
  evidence?: {
    origin_uri: string;
    source_revision: string;
    method: string;
    confidence: number;
    processed_at?: string | null;
  };
  provenance?: Provenance;
}
export interface GraphNeighborhoodParams {
  entity: string;
  depth?: 1 | 2 | 3;
  group_by?: 'type';
}
export interface GraphNeighborhood {
  focus: GraphNode;
  depth: number;
  group_by: string;
  nodes: readonly GraphNode[];
  edges: readonly GraphEdge[];
}
export interface GraphNode {
  id: string;
  type: string;
  label: string;
  color: string;
  weight: number;
  provenance: Provenance;
  description?: string;
  relations_count?: number;
  owner?: string;
  updated_at?: string;
}
export interface GraphEdge {
  origin: string;
  destination: string;
  predicate: string;
  weight: number;
  provenance: Provenance;
}
export interface GraphNodeDetail {
  id: string;
  type: string;
  label: string;
  color: string;
  description: string;
  relations_count: number;
  owner: string | { id: string; type: string };
  updated_at: string;
  provenance: Provenance;
}
export interface GraphDiagnosis {
  summary: string;
  provenance: Provenance;
}
export interface UxDecision {
  id: string;
  type: 'Documento' | 'ADR';
  title: string;
  description: string;
  outcome: string;
  provenance: Provenance;
}
export interface UxDecisionsResponse {
  decisions: readonly UxDecision[];
  provenance: Provenance;
}
export interface Approval {
  id: string;
  title: string;
  provenance: Provenance;
  entity_type_color?: string;
  age?: string;
  author?: { id: string; type: string };
  entity_type?: string;
  origin?: { kind?: string | null; revision?: string | null; detected_by?: string | null };
  [key: string]: unknown;
}
export interface ApprovalsResponse {
  approvals: readonly Approval[];
  low_impact_auto_approval?: { count: number; hours: number };
}
export interface ApprovalDetail extends Approval {
  analysis: { text: string; model?: string | null; confidence: number };
  diff: { current: string; proposed: string };
  impact: { level: string; entities: readonly string[] };
}
export interface ApprovalSuccess {
  approval_id: string;
  decision: 'approved';
  pull_request: { reference: string };
  commit: string;
  reindexing: { status: string; target: string };
}
export interface KnowledgeHealthMetric {
  key: 'coverage' | 'ai_confidence' | 'unanswered_questions' | 'approval_time_hours';
  label: string;
  value: number;
  unit: '%' | 'decimal' | 'count' | 'hours';
  variation: number;
  trend_color: string;
  series: readonly { at: string; value: number }[];
  provenance: Provenance;
}
export interface KnowledgeDebtItem {
  label: string;
  value: number;
  reference_total: number;
  percentage: number;
  color: string;
  provenance: Provenance;
}
export interface UnansweredQuestion {
  id: string;
  question: string;
  occurrence_count: number;
  period: { started_at: string; ended_at: string; days: number };
  color: string;
  provenance: Provenance;
}
export interface KnowledgeHealthInsight {
  low_coverage_threshold: number;
  support_tickets_in_low_coverage_modules: number;
  support_tickets_total: number;
  support_ticket_share: number;
  text: string;
  provenance: Provenance;
}
export interface KnowledgeHealth {
  scope_id: string;
  period: '30d';
  metrics: readonly KnowledgeHealthMetric[];
  knowledge_debt: readonly KnowledgeDebtItem[];
  unanswered_questions: readonly UnansweredQuestion[];
  period_insight: KnowledgeHealthInsight;
  provenance: Provenance;
}
export interface KnowledgeGapAssignment {
  gap_id: string;
  owner: { id: string; type: 'human' };
  documentation_task: { id: string; status: 'open'; provenance: Pick<Provenance, 'method' | 'confidence'> };
}
export interface Insight {
  type: string;
  color: string;
  title: string;
  description: string;
  action: string;
}
export interface InsightsResponse {
  insights: readonly Insight[];
}
export interface HomeData {
  organization: Organization;
  applications: readonly Application[];
  frequent_questions: readonly { id: string; question: string; provenance: Provenance }[];
  top_processes: readonly { id: string; name: string; volume: number; provenance: Provenance }[];
  activity: readonly {
    actor: string;
    action: string;
    occurred_at: string;
    color: string;
    provenance: Provenance;
  }[];
}
export interface CopilotAskInput {
  question: string;
  organization: string;
  application?: string;
  module?: string;
  embedding?: readonly number[];
}
export interface CopilotAnswer {
  opening: string;
  confidence: number;
  source_count: number;
  blocks: readonly CopilotBlock[];
  sources: readonly CopilotSource[];
  entities: readonly CopilotEntity[];
  actions: readonly CopilotAction[];
  reasoning: readonly string[];
  reasoning_trace: unknown;
  [key: string]: unknown;
}
export interface CopilotBlock {
  text: string;
  citation: { revision_id: string; uri: string };
}
export interface CopilotSource {
  type: { name: string; color: string };
  name: string;
  freshness?: string;
  relevance: number;
  provenance: { revision_id: string; uri: string; method: string; confidence: number };
}
export interface CopilotEntity {
  id: string;
  type: string;
  name: string;
  color: string;
}
export interface CopilotAction {
  id: 'deepen_process' | 'view_graph' | 'create_faq' | 'report_error';
  label: string;
}
export type EntityDetail = KnowledgeEntityV1 & {
  provenance?: Provenance;
  freshness?: unknown;
  projection?: unknown;
};
