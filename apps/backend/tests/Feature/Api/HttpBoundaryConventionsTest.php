<?php

declare(strict_types=1);

use App\Http\Api\Pagination\StableCursor;
use App\Http\Api\Problem\DomainProblem;
use Illuminate\Support\Facades\Route;

it('returns RFC 9457 problems with a domain code and propagated correlation id', function (): void {
    $this->getJson('/api/v1/me', ['X-Correlation-Id' => 'corr-api-001'])
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertHeader('X-Correlation-Id', 'corr-api-001')
        ->assertJsonPath('code', 'identity.unauthenticated')
        ->assertJsonPath('correlation_id', 'corr-api-001');
});

it('rejects representations other than JSON at the API boundary', function (): void {
    $this->get('/api/v1/openapi.json', ['Accept' => 'text/html'])
        ->assertNotAcceptable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'request.not_acceptable');
});

it('requires and replays idempotency keys for API commands', function (): void {
    Route::post('/api/v1/testing/idempotency', fn () => response()->json(['created' => true], 201))
        ->middleware(['api.correlation', 'api.negotiate', 'api.rate-limit', 'api.idempotency']);

    $this->postJson('/api/v1/testing/idempotency', [])->assertBadRequest()->assertJsonPath('code', 'command.idempotency_key_required');
    $this->postJson('/api/v1/testing/idempotency', ['value' => 'a'], ['Idempotency-Key' => 'command-001'])
        ->assertCreated()->assertJsonPath('created', true);
    $this->postJson('/api/v1/testing/idempotency', ['value' => 'a'], ['Idempotency-Key' => 'command-001'])
        ->assertCreated()->assertHeader('X-Idempotent-Replay', 'true');
    $this->postJson('/api/v1/testing/idempotency', ['value' => 'b'], ['Idempotency-Key' => 'command-001'])
        ->assertConflict()->assertJsonPath('code', 'command.idempotency_key_reused');
});

it('encodes stable pagination cursors and rejects malformed cursors', function (): void {
    $cursor = StableCursor::encode(['sort' => '2026-07-31T10:00:00Z', 'id' => 'entity-001']);
    expect(StableCursor::decode($cursor))->toBe(['sort' => '2026-07-31T10:00:00Z', 'id' => 'entity-001']);
    expect(fn () => StableCursor::decode('not-a-cursor'))->toThrow(DomainProblem::class);
});

it('publishes OpenAPI 3.1 generated from API operation attributes', function (): void {
    $this->getJson('/api/v1/openapi.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.oai.openapi+json;version=3.1')
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('paths./api/v1/me.get.summary', 'Consultar o principal autenticado');
});
