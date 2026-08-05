import type { GraphNeighborhoodParams, SearchParams } from './types';

const normalize = (params: object) =>
  Object.fromEntries(
    Object.entries(params)
      .filter(([, value]) => value !== undefined)
      .map(([key, value]) => [
        key,
        Array.isArray(value) ? value.map((item: unknown) => item).sort() : value,
      ]),
  );

export const portalQueryKeys = {
  organization: (id: string) => ['organization', id] as const,
  applications: () => ['applications'] as const,
  home: (organization: string) => ['home', organization] as const,
  application: (id: string) => ['application', id] as const,
  applicationTree: (id: string, nav: 'technical' | 'functional') =>
    ['application-tree', id, nav] as const,
  applicationModules: (id: string) => ['application-modules', id] as const,
  entity: (id: string) => ['entity', id] as const,
  search: (params: SearchParams) => ['search', normalize(params)] as const,
  graphNeighborhood: (params: GraphNeighborhoodParams) =>
    ['graph-neighborhood', normalize(params)] as const,
  approvals: () => ['approvals'] as const,
  knowledgeHealth: (scope: string, period: '30d') => ['knowledge-health', scope, period] as const,
  insights: () => ['insights'] as const,
  uxDecisions: () => ['ux-decisions'] as const,
} as const;
