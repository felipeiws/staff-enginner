<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Port\AuditTrail;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

/** Persiste print/vídeo e sua evidência OCR como observação, nunca como publicação automática. */
final readonly class IngestVisualArtifact
{
    public function __construct(private DatabaseManager $database, private Filesystem $storage, private AuditTrail $auditTrail, private VisualArtifactFreshness $freshness) {}

    /** @param array<string, mixed> $input */
    public function handle(array $input, Actor $actor): VisualArtifactResult
    {
        $content = base64_decode((string) $input['content_base64'], true);
        if ($content === false) {
            throw new RuntimeException('content_base64 deve ser Base64 válido.');
        }
        $hash = 'sha256:'.hash('sha256', $content);
        if (! hash_equals($hash, (string) $input['content_hash'])) {
            throw new RuntimeException('content_hash não corresponde ao conteúdo recebido.');
        }
        $source = $this->database->table('sources')->where('type', (string) $input['source_type'])->where('active', true)->first();
        if ($source === null) {
            throw new UnknownSource('Fonte ativa desconhecida: '.$input['source_type'].'.');
        }
        $artifact = $this->database->table('artifacts')->where('content_hash', $hash)->first();
        $revision = $artifact === null ? null : $this->database->table('revisions')->where('artifact_id', $artifact->id)->where('source_revision', (string) $input['provenance']['source_revision'])->first();
        $screen = $this->database->table('entities')->where('entity_type', 'Tela')->where('external_natural_key', (string) $input['screen_key'])->first();
        if ($revision !== null && $screen !== null) {
            $screenRevision = $this->database->table('entity_revisions')->where('entity_id', $screen->id)->where('source_revision_id', $revision->id)->latest('version')->first();
            if ($screenRevision !== null) {
                return new VisualArtifactResult((string) $artifact->id, (string) $revision->id, (string) $screen->id, (string) $screenRevision->id, true);
            }
        }
        $objectPath = trim((string) config('ingestion.visual_object_storage_prefix'), '/').'/'.substr($hash, 7);
        if (! $this->storage->exists($objectPath) && ! $this->storage->put($objectPath, $content)) {
            throw new RuntimeException('Não foi possível persistir o artefato visual no object storage.');
        }
        $thumbnailPath = $this->storeThumbnail($input, $hash);
        $artifactId = $artifact === null ? (string) Str::uuid() : (string) $artifact->id;
        $revisionId = $revision === null ? (string) Str::uuid() : (string) $revision->id;
        $screenId = $screen === null ? (string) Str::uuid() : (string) $screen->id;
        $screenRevisionId = (string) Str::uuid();
        $now = now();
        $freshness = isset($input['code_changed_at']) ? $this->freshness->calculate((string) $input['artifact_at'], (string) $input['code_changed_at']) : null;
        $this->database->transaction(function () use ($input, $actor, $source, $artifact, $revision, $screen, $content, $hash, $objectPath, $thumbnailPath, $artifactId, $revisionId, $screenId, $screenRevisionId, $now, $freshness): void {
            if ($artifact === null) {
                $this->database->table('artifacts')->insert(['id' => $artifactId, 'source_id' => $source->id, 'external_identifier' => 'visual:'.substr($hash, 7), 'uri' => 'object://'.$objectPath, 'content_hash' => $hash, 'media_type' => $input['media_type'], 'size_bytes' => strlen($content), 'object_storage_pointer' => $objectPath, 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($revision === null) {
                $this->database->table('revisions')->insert(['id' => $revisionId, 'artifact_id' => $artifactId, 'source_revision' => $input['provenance']['source_revision'], 'observed_at' => $input['artifact_at'], 'checksum' => $hash, 'metadata' => json_encode(['method' => $input['provenance']['method'], 'processed_at' => $input['provenance']['processed_at'], 'kind' => $input['kind']], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            }
            if ($screen === null) {
                $this->database->table('entities')->insert(['id' => $screenId, 'entity_type' => 'Tela', 'family' => 'software', 'scope' => json_encode($input['scope'], JSON_THROW_ON_ERROR), 'external_natural_key' => $input['screen_key'], 'owner_id' => $actor->actorId, 'owner_type' => $actor->actorType, 'created_at' => $now, 'updated_at' => $now]);
            }
            $version = ((int) $this->database->table('entity_revisions')->where('entity_id', $screenId)->max('version')) + 1;
            $attributes = ['visual_artifact' => ['artifact_id' => $artifactId, 'revision_id' => $revisionId, 'kind' => $input['kind'], 'variant' => $input['variant'], 'media_type' => $input['media_type'], 'object_storage_pointer' => $objectPath, 'thumbnail_pointer' => $thumbnailPath, 'duration_seconds' => $input['duration_seconds'] ?? null, 'dimensions' => $input['dimensions'] ?? null, 'components' => $input['components'] ?? [], 'relationships' => $input['relationships'] ?? [], 'ocr_evidence' => $input['ocr_evidence'], 'provenance' => $input['provenance'], 'freshness' => $freshness]];
            $this->database->table('entity_revisions')->insert(['id' => $screenRevisionId, 'entity_id' => $screenId, 'version' => $version, 'lifecycle_state' => 'observed', 'title' => $input['screen_title'] ?? $input['screen_key'], 'body' => $input['ocr_evidence']['text'], 'attributes' => json_encode($attributes, JSON_THROW_ON_ERROR), 'source_revision_id' => $revisionId, 'confidence' => $input['ocr_evidence']['confidence'], 'created_at' => $now, 'updated_at' => $now]);
            $text = trim((string) $input['ocr_evidence']['text']);
            if ($revision === null && $text !== '') {
                $this->database->table('chunks')->insert(['id' => (string) Str::uuid(), 'revision_id' => $revisionId, 'ordinal' => 0, 'start_offset' => 0, 'end_offset' => mb_strlen($text), 'text' => $text, 'tokens' => max(1, str_word_count($text)), 'checksum' => hash('sha256', $text), 'created_at' => $now, 'updated_at' => $now]);
            }
            $this->auditTrail->record(new AuditRecord($actor, 'ingestion.visual_artifact.received', 'entity_revision', $screenRevisionId, $screenId, $screenRevisionId, $revisionId, 'observed', 'Print ou vídeo versionado com evidência OCR.', null, $input['scope'], null, ['method' => $input['provenance']['method'], 'content_hash' => $hash, 'ocr_confidence' => $input['ocr_evidence']['confidence'], 'freshness' => $freshness], 'internal', $input['provenance']['method'], (float) $input['ocr_evidence']['confidence']));
        });
        return new VisualArtifactResult($artifactId, $revisionId, $screenId, $screenRevisionId, false);
    }

    /** @param array<string, mixed> $input */
    private function storeThumbnail(array $input, string $hash): ?string
    {
        if (! isset($input['thumbnail_base64']) || ! is_string($input['thumbnail_base64'])) return null;
        $thumbnail = base64_decode($input['thumbnail_base64'], true);
        if ($thumbnail === false) throw new RuntimeException('thumbnail_base64 deve ser Base64 válido.');
        $path = trim((string) config('ingestion.visual_object_storage_prefix'), '/').'/'.substr($hash, 7).'.thumbnail.png';
        if (! $this->storage->exists($path) && ! $this->storage->put($path, $thumbnail)) throw new RuntimeException('Não foi possível persistir a miniatura.');
        return $path;
    }
}
