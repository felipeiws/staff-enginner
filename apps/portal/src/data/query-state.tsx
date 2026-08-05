import type { ReactNode } from 'react';
import { HttpProblemError } from '@/lib/http-client';
import './query-state.css';

export function DataSkeleton({ lines = 3 }: { lines?: number }) {
  return (
    <div aria-label="Carregando conteúdo" className="atlas-data-skeleton">
      {Array.from({ length: lines }, (_, index) => (
        <span key={index} />
      ))}
    </div>
  );
}
export function DataError({ error, retry }: { error: unknown; retry?: () => void }) {
  const message =
    error instanceof HttpProblemError
      ? (error.problem.detail ?? error.problem.title)
      : 'Não foi possível carregar estes dados. Tente novamente.';
  return (
    <div className="atlas-data-error" role="alert">
      <span>{message}</span>
      {retry ? (
        <button onClick={retry} type="button">
          Tentar novamente
        </button>
      ) : null}
    </div>
  );
}
export function CopilotRetrievingEvidence() {
  return (
    <div className="atlas-copilot-thinking" role="status">
      <span>✦</span>
      <span>recuperando evidências…</span>
    </div>
  );
}
export function QueryState({
  isLoading,
  error,
  retry,
  children,
}: {
  isLoading: boolean;
  error: unknown;
  retry?: () => void;
  children: ReactNode;
}) {
  if (isLoading) return <DataSkeleton />;
  if (error) return <DataError error={error} {...(retry === undefined ? {} : { retry })} />;
  return children;
}
