import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  askCopilot,
  getApplication,
  getApplicationModules,
  getApplicationTree,
  getApplications,
  getApprovals,
  getApproval,
  approveApproval,
  editApprovalText,
  requestApprovalAiRevision,
  rejectApproval,
  getEntity,
  getGraphNeighborhood,
  getGraphNode,
  getGraphDiagnosis,
  getInsights,
  getHome,
  getKnowledgeHealth,
  assignKnowledgeGap,
  getOrganization,
  getUxDecisions,
  searchKnowledge,
  uploadVisualArtifact,
} from './portal-api';
import { portalQueryKeys } from './query-keys';
import type { CopilotAskInput, GraphNeighborhoodParams, SearchParams } from './types';
import type { VisualArtifactUpload } from './portal-api';

export const isTransientError = (error: unknown) => {
  if (error instanceof TypeError) return true;
  const status =
    typeof error === 'object' && error !== null && 'problem' in error
      ? Number((error as { problem: { status?: number } }).problem.status)
      : 0;
  return status === 408 || status === 425 || status === 429 || status >= 500;
};
const retryTransient = (failureCount: number, error: unknown) =>
  isTransientError(error) && failureCount < 2;

export function useOrganization(id: string) {
  return useQuery({
    queryKey: portalQueryKeys.organization(id),
    queryFn: ({ signal }) => getOrganization(id, signal),
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
export function useApplications() {
  return useQuery({
    queryKey: portalQueryKeys.applications(),
    queryFn: ({ signal }) => getApplications(signal),
    select: (response) => response.applications,
    retry: retryTransient,
  });
}
export function useHome(organization: string) {
  return useQuery({
    queryKey: portalQueryKeys.home(organization),
    queryFn: ({ signal }) => getHome(organization, signal),
    enabled: Boolean(organization),
    retry: retryTransient,
  });
}
export function useApplication(id: string) {
  return useQuery({
    queryKey: portalQueryKeys.application(id),
    queryFn: ({ signal }) => getApplication(id, signal),
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
export function useApplicationTree(id: string, nav: 'technical' | 'functional') {
  return useQuery({
    queryKey: portalQueryKeys.applicationTree(id, nav),
    queryFn: ({ signal }) => getApplicationTree(id, nav, signal),
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
export function useApplicationModules(id: string) {
  return useQuery({
    queryKey: portalQueryKeys.applicationModules(id),
    queryFn: ({ signal }) => getApplicationModules(id, signal),
    select: (response) => response.modules,
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
export function useEntity(id: string) {
  return useQuery({
    queryKey: portalQueryKeys.entity(id),
    queryFn: ({ signal }) => getEntity(id, signal),
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
export function useSearch(params: SearchParams) {
  return useQuery({
    queryKey: portalQueryKeys.search(params),
    queryFn: ({ signal }) => searchKnowledge(params, signal),
    enabled: Boolean(params.q && params.organization),
    retry: retryTransient,
  });
}
export function useGraphNeighborhood(params: GraphNeighborhoodParams) {
  return useQuery({
    queryKey: portalQueryKeys.graphNeighborhood(params),
    queryFn: ({ signal }) => getGraphNeighborhood(params, signal),
    enabled: Boolean(params.entity),
    retry: retryTransient,
  });
}
export function useGraphNode(id: string) {
  return useQuery({
    queryKey: ['graph-node', id],
    queryFn: ({ signal }) => getGraphNode(id, signal),
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
export function useGraphDiagnosis(scope: string) {
  return useQuery({
    queryKey: ['graph-diagnosis', scope],
    queryFn: ({ signal }) => getGraphDiagnosis(scope, signal),
    enabled: Boolean(scope),
    retry: retryTransient,
  });
}
export function useApprovals() {
  return useQuery({
    queryKey: portalQueryKeys.approvals(),
    queryFn: ({ signal }) => getApprovals(signal),
    retry: retryTransient,
  });
}
export function useApproval(id: string) {
  return useQuery({
    queryKey: ['approval', id],
    queryFn: ({ signal }) => getApproval(id, signal),
    enabled: Boolean(id),
    retry: retryTransient,
  });
}
function useApprovalMutation<T>(mutationFn: (input: { id: string; value: string }) => Promise<T>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn,
    onSuccess: async (_result, input) => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: portalQueryKeys.approvals() }),
        queryClient.invalidateQueries({ queryKey: ['approval', input.id] }),
      ]);
    },
  });
}
export function useApproveApproval() {
  return useApprovalMutation(({ id, value }) => approveApproval(id, value, crypto.randomUUID()));
}
export function useEditApprovalText() {
  return useApprovalMutation(({ id, value }) => editApprovalText(id, value, crypto.randomUUID()));
}
export function useRequestApprovalAiRevision() {
  return useApprovalMutation(({ id, value }) => requestApprovalAiRevision(id, value, crypto.randomUUID()));
}
export function useRejectApproval() {
  return useApprovalMutation(({ id, value }) => rejectApproval(id, value, crypto.randomUUID()));
}
export function useKnowledgeHealth(scope: string, period: '30d' = '30d') {
  return useQuery({
    queryKey: portalQueryKeys.knowledgeHealth(scope, period),
    queryFn: ({ signal }) => getKnowledgeHealth(scope, period, signal),
    enabled: Boolean(scope),
    staleTime: 5 * 60_000,
    retry: retryTransient,
  });
}
export function useAssignKnowledgeGap() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      ownerId,
      scope,
    }: {
      id: string;
      ownerId: string;
      scope: { organization: string; application?: string; module?: string };
    }) => assignKnowledgeGap(id, { owner_id: ownerId, ...scope }, crypto.randomUUID()),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['knowledge-health'] });
    },
  });
}
export function useInsights() {
  return useQuery({
    queryKey: portalQueryKeys.insights(),
    queryFn: ({ signal }) => getInsights(signal),
    retry: retryTransient,
  });
}
export function useUxDecisions() {
  return useQuery({
    queryKey: portalQueryKeys.uxDecisions(),
    queryFn: ({ signal }) => getUxDecisions(signal),
    retry: retryTransient,
  });
}
export function useCopilotAsk() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({
      input,
      onEvent,
    }: {
      input: CopilotAskInput;
      onEvent?: (event: unknown) => void;
    }) => askCopilot(input, crypto.randomUUID(), onEvent ?? (() => undefined)),
    onSettled: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: portalQueryKeys.insights() }),
        queryClient.invalidateQueries({ queryKey: ['knowledge-health'] }),
      ]);
    },
  });
}

export function useVisualArtifactUpload() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (input: VisualArtifactUpload) => uploadVisualArtifact(input, crypto.randomUUID()),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['entity'] });
    },
  });
}
