import { AtlasHttpClient } from '@/lib/http-client';
import type {
  Application,
  ApplicationModule,
  ApplicationTree,
  ApprovalsResponse,
  ApprovalDetail,
  ApprovalSuccess,
  CopilotAnswer,
  CopilotAskInput,
  EntityDetail,
  GraphNeighborhood,
  GraphNeighborhoodParams,
  GraphNodeDetail,
  GraphDiagnosis,
  HomeData,
  InsightsResponse,
  KnowledgeHealth,
  KnowledgeGapAssignment,
  Organization,
  Principal,
  SearchParams,
  SearchResponse,
  UxDecisionsResponse,
} from './types';

export const portalApi = new AtlasHttpClient();
const query = (params: object) => {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined) return;
    const parameter = Array.isArray(value) ? `${key}[]` : key;
    (Array.isArray(value) ? value : [value]).forEach((item) => search.append(parameter, String(item)));
  });
  return search.toString() ? `?${search}` : '';
};

export const getCurrentPrincipal = (signal?: AbortSignal) =>
  portalApi.get<{ principal: Principal }>('/me', signal);
export const getOrganization = (id: string, signal?: AbortSignal) =>
  portalApi.get<Organization>(`/organizations/${encodeURIComponent(id)}`, signal);
export const getApplications = (signal?: AbortSignal) =>
  portalApi.get<{ applications: readonly Application[] }>('/applications', signal);
export const getHome = (organization: string, signal?: AbortSignal) =>
  portalApi.get<HomeData>(`/home${query({ organization })}`, signal);
export const getApplication = (id: string, signal?: AbortSignal) =>
  portalApi.get<Application>(`/applications/${encodeURIComponent(id)}`, signal);
export const getApplicationTree = (
  id: string,
  nav: 'technical' | 'functional',
  signal?: AbortSignal,
) =>
  portalApi.get<ApplicationTree>(
    `/applications/${encodeURIComponent(id)}/tree${query({ nav })}`,
    signal,
  );
export const getApplicationModules = (id: string, signal?: AbortSignal) =>
  portalApi.get<{ modules: readonly ApplicationModule[] }>(
    `/applications/${encodeURIComponent(id)}/modules`,
    signal,
  );
export const getEntity = (id: string, signal?: AbortSignal) =>
  portalApi.get<EntityDetail>(`/entities/${encodeURIComponent(id)}`, signal);
export const searchKnowledge = (params: SearchParams, signal?: AbortSignal) =>
  portalApi.get<SearchResponse>(`/search${query(params)}`, signal);
export const getGraphNeighborhood = (params: GraphNeighborhoodParams, signal?: AbortSignal) =>
  portalApi.get<GraphNeighborhood>(
    `/graph/neighborhood${query({ depth: 1, group_by: 'type', ...params })}`,
    signal,
  );
export const getGraphNode = (id: string, signal?: AbortSignal) =>
  portalApi.get<GraphNodeDetail>(`/graph/nodes/${encodeURIComponent(id)}`, signal);
export const getGraphDiagnosis = (scope: string, signal?: AbortSignal) =>
  portalApi.get<GraphDiagnosis>(`/graph/diagnosis${query({ scope })}`, signal);
export const getApprovals = (signal?: AbortSignal) =>
  portalApi.get<ApprovalsResponse>('/approvals', signal);
export const getApproval = (id: string, signal?: AbortSignal) =>
  portalApi.get<ApprovalDetail>(`/approvals/${encodeURIComponent(id)}`, signal);
export const approveApproval = (id: string, justification: string, idempotencyKey: string) =>
  portalApi.post<ApprovalSuccess>(`/approvals/${encodeURIComponent(id)}/approve`, { justification }, idempotencyKey);
export const editApprovalText = (id: string, proposedText: string, idempotencyKey: string) =>
  portalApi.patch<{ approval_id: string; status: string; proposed_text: string }>(`/approvals/${encodeURIComponent(id)}`, { proposed_text: proposedText }, idempotencyKey);
export const requestApprovalAiRevision = (id: string, instruction: string, idempotencyKey: string) =>
  portalApi.post<{ approval_id: string; status: string }>(`/approvals/${encodeURIComponent(id)}/request-ai-revision`, { instruction }, idempotencyKey);
export const rejectApproval = (id: string, reason: string, idempotencyKey: string) =>
  portalApi.post<{ approval_id: string; decision: 'rejected' }>(`/approvals/${encodeURIComponent(id)}/reject`, { reason }, idempotencyKey);
export const getKnowledgeHealth = (scope: string, period: '30d', signal?: AbortSignal) =>
  portalApi.get<KnowledgeHealth>(`/knowledge-health${query({ scope, period })}`, signal);
export const assignKnowledgeGap = (
  id: string,
  input: { owner_id: string; organization: string; application?: string; module?: string },
  idempotencyKey: string,
) => portalApi.post<KnowledgeGapAssignment>(`/knowledge-gaps/${encodeURIComponent(id)}/assign`, input, idempotencyKey);
export const getInsights = (signal?: AbortSignal) =>
  portalApi.get<InsightsResponse>('/insights', signal);
export const getUxDecisions = (signal?: AbortSignal) =>
  portalApi.get<UxDecisionsResponse>('/ux-decisions', signal);
export const askCopilot = (
  input: CopilotAskInput,
  idempotencyKey: string,
  onEvent: (event: unknown) => void,
) => portalApi.postStream<CopilotAnswer>('/copilot/ask', input, idempotencyKey, onEvent);

export interface VisualArtifactUpload {
  file: File;
  screenKey: string;
  screenTitle: string;
  variant: 'desktop' | 'mobile';
  kind: 'print' | 'video';
  scope: Record<string, string>;
}

/** O backend recebe o arquivo como observação; ele não publica a Tela automaticamente. */
export const uploadVisualArtifact = (input: VisualArtifactUpload, idempotencyKey: string) => {
  const body = new FormData();
  body.set('file', input.file);
  body.set('screen_key', input.screenKey);
  body.set('screen_title', input.screenTitle);
  body.set('variant', input.variant);
  body.set('kind', input.kind);
  body.set('media_type', input.file.type || (input.kind === 'video' ? 'video/mp4' : 'image/png'));
  body.set('source_revision', `manual:${new Date().toISOString()}`);
  body.set('artifact_at', new Date().toISOString());
  Object.entries(input.scope).forEach(([key, value]) => body.set(`scope[${key}]`, value));
  body.set('ocr_evidence[text]', 'Extração pendente de processamento pelo artifact.reader.');
  body.set('ocr_evidence[method]', 'manual.upload@1.0.0');
  body.set('ocr_evidence[confidence]', '0');
  body.set('ocr_evidence[processed_at]', new Date().toISOString());
  return portalApi.postForm<{ screen_revision_id: string }>('/visual-artifacts', body, idempotencyKey);
};
