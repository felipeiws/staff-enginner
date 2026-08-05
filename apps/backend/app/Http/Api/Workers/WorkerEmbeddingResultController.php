<?php

declare(strict_types=1);

namespace App\Http\Api\Workers;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Modules\Identity\Domain\Principal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Receber resultado idempotente de embeddings de worker autorizado', ['Workers', 'Busca'], [201 => 'Embeddings persistidos', 403 => 'Escopo não autorizado', 422 => 'Resultado inválido'])]
final class WorkerEmbeddingResultController
{
    public function __invoke(Request $request, PersistWorkerEmbeddingResult $results): JsonResponse
    {
        $validated = $request->validate([
            'schema_version' => ['required', 'in:1.0.0'], 'event_type' => ['required', 'in:EmbeddingGenerated'],
            'revision_id' => ['required', 'uuid'], 'task_id' => ['required', 'uuid'], 'scope' => ['required', 'array'],
            'provenance' => ['required', 'array'], 'provenance.source_revision_id' => ['required', 'uuid', 'same:revision_id'],
            'provenance.method' => ['required', 'string', 'max:255'], 'provenance.processed_at' => ['required', 'date'],
            'chunks' => ['required', 'array', 'min:1'],
            'chunks.*.ordinal' => ['required', 'integer', 'min:0'], 'chunks.*.start_offset' => ['required', 'integer', 'min:0'], 'chunks.*.end_offset' => ['required', 'integer', 'min:1'],
            'chunks.*.text' => ['required', 'string', 'max:200000'], 'chunks.*.tokens' => ['required', 'integer', 'min:1'], 'chunks.*.checksum' => ['required', 'string', 'size:64'],
            'chunks.*.embedding' => ['required', 'array', 'min:1'], 'chunks.*.embedding.*' => ['numeric'], 'chunks.*.model' => ['required', 'string', 'max:255'],
            'chunks.*.model_version' => ['required', 'string', 'max:255'], 'chunks.*.parameters' => ['required', 'array'], 'chunks.*.cost' => ['nullable', 'numeric', 'min:0'], 'chunks.*.cost_currency' => ['nullable', 'string', 'size:3'],
        ]);
        /** @var Principal $principal */
        $principal = $request->attributes->get('identity.principal');
        $results->persist($principal, $validated);

        return response()->json(['revision_id' => $validated['revision_id'], 'status' => 'persisted'], 201);
    }
}
