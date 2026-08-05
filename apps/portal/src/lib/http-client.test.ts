import { describe, expect, it } from 'vitest';
import { AtlasHttpClient } from '@/lib/http-client';

describe('AtlasHttpClient', () => {
  it('normaliza um problem RFC 9457', async () => {
    const client = new AtlasHttpClient({
      baseUrl: 'http://atlas.test/api/v1',
      fetchFn: () =>
        Promise.resolve(
          new Response(
            JSON.stringify({ title: 'Escopo negado', status: 403, detail: 'Sem permissão.' }),
            { status: 403 },
          ),
        ),
    });
    await expect(client.get('/busca')).rejects.toMatchObject({
      name: 'HttpProblemError',
      problem: { title: 'Escopo negado', status: 403, detail: 'Sem permissão.' },
    });
  });
});
