/** Browser-side OpenTelemetry W3C context. Never serializes UI content or credentials. */
export function correlationId(): string {
  const value = globalThis.crypto?.randomUUID?.();
  return value ?? `portal-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function traceparent(): string {
  const bytes = new Uint8Array(24);
  globalThis.crypto?.getRandomValues?.(bytes);
  const hex = Array.from(bytes, (item) => item.toString(16).padStart(2, '0')).join('');
  return `00-${hex.slice(0, 32).padEnd(32, '0')}-${hex.slice(32).padEnd(16, '0')}-01`;
}

export function logPortalSpan(route: string, durationMs: number, status: number): void {
  console.info(JSON.stringify({ timestamp: new Date().toISOString(), service: 'portal', event: 'otel.span.completed', route, duration_ms: Math.round(durationMs), status }));
}
