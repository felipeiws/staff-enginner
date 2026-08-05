import type { KnowledgeEntityV1 } from '@atlas/contracts';
import { correlationId, logPortalSpan, traceparent } from '@/lib/telemetry';

export interface ProblemDetails {
  type?: string;
  title: string;
  status: number;
  detail?: string;
  instance?: string;
  [extension: string]: unknown;
}

export class HttpProblemError extends Error {
  public constructor(public readonly problem: ProblemDetails) {
    super(problem.detail ?? problem.title);
    this.name = 'HttpProblemError';
  }
}

export interface HttpClientOptions {
  baseUrl?: string;
  fetchFn?: typeof fetch;
}

/** Borda HTTP do Portal: os payloads de conhecimento usam tipos de @atlas/contracts. */
export class AtlasHttpClient {
  private readonly baseUrl: string;
  private readonly fetchFn: typeof fetch;

  public constructor({
    baseUrl = '/api/v1',
    fetchFn = (input: RequestInfo | URL, init?: RequestInit) => globalThis.fetch(input, init),
  }: HttpClientOptions = {}) {
    this.baseUrl =
      baseUrl.startsWith('/') && typeof window !== 'undefined'
        ? new URL(baseUrl, window.location.origin).toString().replace(/\/$/, '')
        : baseUrl.replace(/\/$/, '');
    this.fetchFn = fetchFn;
  }

  public async get<TResponse>(path: string, signal?: AbortSignal): Promise<TResponse> {
    return this.request<TResponse>(path, signal === undefined ? {} : { signal });
  }

  public async post<TResponse>(
    path: string,
    body: unknown,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<TResponse> {
    return this.request<TResponse>(path, {
      body: JSON.stringify(body),
      headers: { 'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey },
      method: 'POST',
      ...(signal === undefined ? {} : { signal }),
    });
  }

  public async patch<TResponse>(
    path: string,
    body: unknown,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<TResponse> {
    return this.request<TResponse>(path, {
      body: JSON.stringify(body),
      headers: { 'Content-Type': 'application/json', 'Idempotency-Key': idempotencyKey },
      method: 'PATCH',
      ...(signal === undefined ? {} : { signal }),
    });
  }

  /** Envia artefatos binários sem contornar a borda HTTP autorizada da API. */
  public async postForm<TResponse>(
    path: string,
    body: FormData,
    idempotencyKey: string,
    signal?: AbortSignal,
  ): Promise<TResponse> {
    return this.request<TResponse>(path, {
      body,
      headers: { 'Idempotency-Key': idempotencyKey },
      method: 'POST',
      ...(signal === undefined ? {} : { signal }),
    });
  }

  /** Consome SSE quando disponível, mantendo JSON como fallback compatível com a API v1. */
  public async postStream<TResponse>(
    path: string,
    body: unknown,
    idempotencyKey: string,
    onEvent: (event: unknown) => void,
    signal?: AbortSignal,
  ): Promise<TResponse> {
    const response = await this.fetchFn(`${this.baseUrl}${path}`, {
      body: JSON.stringify(body),
      headers: {
        Accept: 'text/event-stream, application/json, application/problem+json',
        'Content-Type': 'application/json',
        'Idempotency-Key': idempotencyKey,
        'X-Correlation-Id': correlationId(),
        traceparent: traceparent(),
      },
      method: 'POST',
      ...(signal === undefined ? {} : { signal }),
    });
    if (!response.ok) throw new HttpProblemError(await readProblem(response));
    if (!response.headers.get('content-type')?.includes('text/event-stream')) {
      return (await response.json()) as TResponse;
    }
    return readEventStream<TResponse>(response, onEvent);
  }

  public getKnowledgeEntity(entityId: string, signal?: AbortSignal): Promise<KnowledgeEntityV1> {
    return this.get<KnowledgeEntityV1>(`/entities/${encodeURIComponent(entityId)}`, signal);
  }

  private async request<TResponse>(path: string, init: RequestInit = {}): Promise<TResponse> {
    const startedAt = performance.now();
    const response = await this.fetchFn(`${this.baseUrl}${path}`, {
      ...init,
      headers: {
        Accept: 'application/json, application/problem+json',
        'X-Correlation-Id': correlationId(),
        traceparent: traceparent(),
        ...init.headers,
      },
    });
    logPortalSpan(path, performance.now() - startedAt, response.status);
    if (!response.ok) throw new HttpProblemError(await readProblem(response));
    return (await response.json()) as TResponse;
  }
}

async function readEventStream<TResponse>(
  response: Response,
  onEvent: (event: unknown) => void,
): Promise<TResponse> {
  if (response.body === null) throw new Error('A resposta em streaming não possui conteúdo.');
  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = '';
  let result: TResponse | undefined;
  for (;;) {
    const chunk = await reader.read();
    if (chunk.done) break;
    buffer += decoder.decode(chunk.value, { stream: true });
    const events = buffer.split(/\n\n/);
    buffer = events.pop() ?? '';
    for (const event of events) {
      const data = event
        .split(/\n/)
        .filter((line) => line.startsWith('data:'))
        .map((line) => line.slice(5).trim())
        .join('\n');
      if (!data || data === '[DONE]') continue;
      const payload = JSON.parse(data) as { result?: TResponse };
      onEvent(payload);
      if (payload.result !== undefined) result = payload.result;
    }
  }
  if (result === undefined) throw new Error('O Copiloto encerrou sem uma resposta final.');
  return result;
}

async function readProblem(response: Response): Promise<ProblemDetails> {
  try {
    const payload = (await response.json()) as Partial<ProblemDetails>;
    return {
      title: payload.title ?? 'Não foi possível concluir a solicitação.',
      status: payload.status ?? response.status,
      ...(payload.type === undefined ? {} : { type: payload.type }),
      ...(payload.detail === undefined ? {} : { detail: payload.detail }),
      ...(payload.instance === undefined ? {} : { instance: payload.instance }),
    };
  } catch {
    return { title: 'Não foi possível concluir a solicitação.', status: response.status };
  }
}
