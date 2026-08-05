<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Http;

use App\Http\Api\OpenApi\ApiOperation;
use Atlas\Contracts\Dto\Actor;
use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Atlas\Modules\Identity\Domain\Principal;
use Atlas\Modules\Ingestion\Application\IngestVisualArtifact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[ApiOperation('Enviar print ou vídeo versionado para uma Tela', ['Ingestão', 'Artefatos'], [201 => 'Artefato observado', 403 => 'Escopo não autorizado', 422 => 'Artefato inválido'])]
final class VisualArtifactController
{
    public function __invoke(Request $request, IngestVisualArtifact $ingestion, AuthorizationService $authorization): JsonResponse
    {
        $validated = $request->validate([
            'screen_key' => ['required', 'string', 'max:255'], 'screen_title' => ['nullable', 'string', 'max:255'], 'variant' => ['required', 'in:desktop,mobile'], 'kind' => ['required', 'in:print,video'],
            'content_base64' => ['required_without:file', 'string'], 'file' => ['required_without:content_base64', 'file', 'max:51200'], 'content_hash' => ['nullable', 'regex:/^sha256:[a-f0-9]{64}$/'], 'media_type' => ['required', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:255'], 'source_revision' => ['required', 'string', 'max:255'], 'artifact_at' => ['required', 'date'], 'code_changed_at' => ['nullable', 'date'],
            'scope' => ['required', 'array'], 'scope.*' => ['string'], 'duration_seconds' => ['nullable', 'numeric', 'min:0'], 'components' => ['nullable', 'array'], 'relationships' => ['nullable', 'array'],
            'thumbnail_base64' => ['nullable', 'string'], 'dimensions' => ['nullable', 'array'], 'ocr_evidence' => ['required', 'array'], 'ocr_evidence.text' => ['required', 'string'], 'ocr_evidence.method' => ['required', 'string'], 'ocr_evidence.confidence' => ['required', 'numeric', 'min:0', 'max:1'], 'ocr_evidence.processed_at' => ['required', 'date'], 'provenance' => ['nullable', 'array'],
        ]);
        if ($request->hasFile('file')) $validated['content_base64'] = base64_encode((string) $request->file('file')->get());
        $content = base64_decode((string) $validated['content_base64'], true);
        if ($content === false) return response()->json(['message' => 'content_base64 inválido.'], 422);
        $validated['content_hash'] ??= 'sha256:'.hash('sha256', $content);
        $validated['source_type'] ??= 'visual_upload';
        $validated['provenance'] ??= ['source_revision' => $validated['source_revision'], 'method' => 'manual.upload@1.0.0', 'processed_at' => now()->toISOString()];
        /** @var Principal $principal */
        $principal = $request->attributes->get('identity.principal');
        $authorization->assertAuthorized($principal, new AuthorizationRequest('knowledge.contribute', $validated['scope'], 'internal'));
        $result = $ingestion->handle($validated, new Actor($principal->id, $principal->type));

        return response()->json($result->toArray(), $result->replayed ? 200 : 201);
    }
}
