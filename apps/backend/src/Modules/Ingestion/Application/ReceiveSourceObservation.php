<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Dto\SourceObservation;
use Atlas\Contracts\Factory\ContractFactory;
use Atlas\Contracts\Port\AuditTrail;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use RuntimeException;

/** Recebe uma observação de fonte e cria a revisão observada de forma atômica. */
final readonly class ReceiveSourceObservation
{
    public function __construct(
        private ContractFactory $contracts,
        private DatabaseManager $database,
        private Filesystem $storage,
        private AuditTrail $auditTrail,
        private ContentClassificationGate $classificationGate,
    ) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload): SourceObservationResult
    {
        $observation = $this->contracts->sourceObservation($payload);
        $source = $this->database->table('sources')->where('type', $observation->sourceType)->where('active', true)->first();
        if ($source === null) {
            throw new UnknownSource("Fonte ativa desconhecida: {$observation->sourceType}.");
        }
        $assessment = $this->classificationGate->assess($observation->content, (string) $source->classification, $observation->metadata);
        $existing = $this->database->table('observations')->where('idempotency_key', $observation->command->idempotencyKey)->first();

        if ($existing !== null) {
            if ($this->canonicalHash($this->payload($observation, $assessment['redacted_content'])) !== $this->canonicalHash($this->record($existing->payload))) {
                throw new SourceObservationConflict('A idempotency key já foi usada com um payload diferente.');
            }

            return $this->resultForObservation((string) $existing->id, true);
        }

        $artifact = $this->database->table('artifacts')->where('source_id', (string) $source->id)->where('external_identifier', $observation->externalId)->first();
        $existingRevision = $artifact === null ? null : $this->database->table('revisions')->where('artifact_id', $artifact->id)->where('source_revision', $observation->revision)->first();
        if ($existingRevision !== null) {
            if ((string) $existingRevision->checksum !== $observation->checksum) {
                throw new SourceObservationConflict('A mesma revisão da fonte foi recebida com conteúdo/checksum diferente.');
            }

            return $this->resultForSourceRevision((string) $existingRevision->id, true);
        }

        [$contentHash, $pointer, $inlineBody, $mediaType] = $this->prepareContent($observation, $assessment['redacted_content']);
        $artifactId = $artifact === null ? (string) Str::uuid() : (string) $artifact->id;
        $sourceRevisionId = (string) Str::uuid();
        $entityType = $this->entityType($observation);
        $entity = $this->database->table('entities')->where('entity_type', $entityType)->where('external_natural_key', $this->normalizedIdentity($observation))->first();
        $entityId = $entity === null ? (string) Str::uuid() : (string) $entity->id;
        $entityRevisionId = (string) Str::uuid();
        $entityVersion = ((int) $this->database->table('entity_revisions')->where('entity_id', $entityId)->max('version')) + 1;
        $result = new SourceObservationResult($observation->observationId, $artifactId, $sourceRevisionId, $entityId, $entityRevisionId, false);
        $scope = $this->scope($source->scope, $observation);
        $event = $this->event($observation, $result, $scope);
        $this->contracts->domainEvent($event);

        $this->database->transaction(function () use ($observation, $source, $scope, $artifact, $artifactId, $sourceRevisionId, $contentHash, $pointer, $mediaType, $entity, $entityId, $entityRevisionId, $entityVersion, $inlineBody, $result, $event, $entityType, $assessment): void {
            $now = now();
            if ($artifact === null) {
                $this->database->table('artifacts')->insert(['id' => $artifactId, 'source_id' => (string) $source->id, 'external_identifier' => $observation->externalId, 'uri' => $observation->uri, 'content_hash' => $contentHash, 'media_type' => $mediaType, 'size_bytes' => $observation->content === null ? null : strlen($observation->content), 'object_storage_pointer' => $pointer, 'created_at' => $now, 'updated_at' => $now]);
            } else {
                $this->database->table('artifacts')->where('id', $artifactId)->update(['uri' => $observation->uri, 'content_hash' => $contentHash, 'media_type' => $mediaType, 'size_bytes' => $observation->content === null ? null : strlen($observation->content), 'object_storage_pointer' => $pointer, 'updated_at' => $now]);
            }

            $this->database->table('revisions')->insert(['id' => $sourceRevisionId, 'artifact_id' => $artifactId, 'source_revision' => $observation->revision, 'observed_at' => $observation->observedAt, 'checksum' => $observation->checksum, 'metadata' => json_encode($observation->metadata, JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            $this->database->table('observations')->insert(['id' => $observation->observationId, 'source_id' => (string) $source->id, 'artifact_id' => $artifactId, 'payload' => json_encode($this->payload($observation, $assessment['redacted_content']), JSON_THROW_ON_ERROR), 'idempotency_key' => $observation->command->idempotencyKey, 'correlation_id' => $observation->command->correlationId, 'actor_id' => $observation->command->actor->actorId, 'actor_type' => $observation->command->actor->actorType, 'processing_status' => $assessment['quarantined'] ? 'quarantined' : 'observed', 'processed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            if ($entity === null) {
                $this->database->table('entities')->insert(['id' => $entityId, 'entity_type' => $entityType, 'family' => $this->entityFamily($entityType), 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'external_natural_key' => $this->normalizedIdentity($observation), 'owner_id' => $this->ownerId($observation), 'owner_type' => $this->ownerType($observation), 'created_at' => $now, 'updated_at' => $now]);
            }
            $this->database->table('entity_revisions')->insert(['id' => $entityRevisionId, 'entity_id' => $entityId, 'version' => $entityVersion, 'lifecycle_state' => 'observed', 'title' => $this->title($observation), 'body' => $inlineBody, 'attributes' => json_encode($this->attributes($observation, $pointer, $now->format(DATE_ATOM)) + ['classification' => $assessment['classification'], 'classification_method' => $assessment['method'], 'contains_personal_data' => $assessment['contains_personal_data'], 'quarantined' => $assessment['quarantined']], JSON_THROW_ON_ERROR), 'source_revision_id' => $sourceRevisionId, 'confidence' => 1.0, 'created_at' => $now, 'updated_at' => $now]);
            $this->database->table('content_classifications')->insert(['id' => (string) Str::uuid(), 'entity_revision_id' => $entityRevisionId, 'source_revision_id' => $sourceRevisionId, 'classification' => $assessment['classification'], 'source_classification' => $assessment['source_classification'], 'decision_method' => $assessment['method'], 'confidence' => $assessment['confidence'], 'contains_personal_data' => $assessment['contains_personal_data'], 'quarantined' => $assessment['quarantined'], 'findings' => json_encode($assessment['findings'], JSON_THROW_ON_ERROR), 'provenance' => json_encode(['source' => 'ingestion', 'revision' => $observation->revision, 'processed_at' => $now->format(DATE_ATOM)], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            if ($assessment['quarantined']) { $this->database->table('security_alerts')->insert(['id' => (string) Str::uuid(), 'entity_revision_id' => $entityRevisionId, 'recipient_id' => $this->ownerId($observation), 'recipient_type' => $this->ownerType($observation), 'kind' => 'secret_detected', 'status' => 'open', 'details' => json_encode(['finding_kinds' => array_values(array_unique(array_column($assessment['findings'], 'kind'))), 'source_revision' => $observation->revision], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]); }
            $this->database->table('executions')->insert(['id' => (string) Str::uuid(), 'observation_id' => $observation->observationId, 'revision_id' => $sourceRevisionId, 'executor_type' => 'ingestion', 'executor_name' => config('ingestion.extractor_name'), 'executor_version' => config('ingestion.extractor_version'), 'parameters' => json_encode(['inline_content_limit_bytes' => config('ingestion.inline_content_limit_bytes')], JSON_THROW_ON_ERROR), 'status' => 'succeeded', 'result' => json_encode($result->toArray(), JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            if (! $assessment['quarantined']) { $this->database->table('outbox_events')->insert(['event_id' => $event['event_id'], 'aggregate' => 'knowledge_revision', 'aggregate_id' => $entityId, 'tipo' => $event['event_type'], 'schema_version' => $event['schema_version'], 'payload' => json_encode($event, JSON_THROW_ON_ERROR), 'occurred_at' => $event['occurred_at'], 'correlation_id' => $event['correlation_id'], 'ator' => json_encode($event['actor'], JSON_THROW_ON_ERROR), 'status' => 'pending', 'tentativas' => 0, 'available_at' => $now, 'created_at' => $now, 'updated_at' => $now]); }
            $this->auditTrail->record(new AuditRecord(new Actor($observation->command->actor->actorId, $observation->command->actor->actorType), 'ingestion.observation.received', 'entity_revision', $entityRevisionId, $entityId, $entityRevisionId, $sourceRevisionId, 'observed', 'Observação de fonte recebida e versionada.', $observation->command->correlationId, $scope, null, ['lifecycle_state' => 'observed', 'source_observation_id' => $observation->observationId, 'source_revision' => $observation->revision, 'method' => $this->method(), 'confidence' => 1.0], $observation->classification, $this->method(), 1.0));
        });

        return $result;
    }

    /** @return array{string, ?string, ?string, string} */
    private function prepareContent(SourceObservation $observation, ?string $safeContent): array
    {
        if ($observation->content === null) {
            $contentRef = $observation->contentRef;
            if ($contentRef === null) {
                throw new RuntimeException('Observação sem conteúdo ou referência de conteúdo.');
            }

            return [$contentRef->checksum, $contentRef->uri, null, $contentRef->mediaType ?? 'application/octet-stream'];
        }

        $safeContent ??= $observation->content;
        $hash = hash('sha256', $safeContent);
        $contentHash = "sha256:{$hash}";
        if (strlen($safeContent) <= (int) config('ingestion.inline_content_limit_bytes')) {
            return [$contentHash, null, $safeContent, 'text/plain'];
        }

        $path = trim((string) config('ingestion.object_storage_prefix'), '/')."/{$hash}";
        if (! $this->storage->put($path, $safeContent)) {
            throw new RuntimeException('Não foi possível persistir o conteúdo bruto no object storage.');
        }

        return [$contentHash, $path, null, 'text/plain'];
    }

    private function normalizedIdentity(SourceObservation $observation): string
    {
        return strtolower(trim($observation->sourceType)).'://'.strtolower(trim($observation->externalId));
    }

    private function title(SourceObservation $observation): string
    {
        return is_string($observation->metadata['title'] ?? null) && trim($observation->metadata['title']) !== '' ? $observation->metadata['title'] : $observation->externalId;
    }

    private function entityType(SourceObservation $observation): string
    {
        $type = $observation->metadata['entity_type'] ?? 'Documento';
        $types = ['Organização', 'Espaço', 'Aplicação', 'Módulo', 'Processo', 'Responsável', 'Integração', 'Repositório', 'Endpoint', 'Controller', 'Service', 'Job', 'Evento', 'Tabela', 'Migration', 'Tela', 'Regra', 'Documento', 'ADR', 'FAQ', 'Comentário', 'Issue', 'Release', 'Vídeo', 'Print', 'Diagrama', 'Fonte', 'Artefato', 'Revisão', 'Chunk', 'Evidência', 'Observação', 'Execução', 'Classificação', 'Política', 'Aprovação', 'Auditoria'];
        if (! is_string($type) || ! in_array($type, $types, true)) {
            throw new RuntimeException('metadata.entity_type não pertence ao vocabulário do Knowledge Graph.');
        }

        return $type;
    }

    private function entityFamily(string $type): string
    {
        return match ($type) {
            'Organização', 'Espaço', 'Aplicação', 'Módulo', 'Processo', 'Responsável', 'Integração' => 'contexto',
            'Repositório', 'Endpoint', 'Controller', 'Service', 'Job', 'Evento', 'Tabela', 'Migration', 'Tela', 'Regra' => 'software',
            'Fonte', 'Artefato', 'Revisão', 'Chunk', 'Evidência', 'Observação', 'Execução' => 'proveniencia',
            'Classificação', 'Política', 'Aprovação', 'Auditoria' => 'governanca',
            default => 'conhecimento',
        };
    }

    private function ownerId(SourceObservation $observation): string
    {
        return is_string($observation->metadata['owner_id'] ?? null) && trim($observation->metadata['owner_id']) !== '' ? $observation->metadata['owner_id'] : $observation->command->actor->actorId;
    }

    private function ownerType(SourceObservation $observation): string
    {
        return is_string($observation->metadata['owner_type'] ?? null) && trim($observation->metadata['owner_type']) !== '' ? $observation->metadata['owner_type'] : $observation->command->actor->actorType;
    }

    /** @return array<string, mixed> */
    private function attributes(SourceObservation $observation, ?string $pointer, string $processedAt): array
    {
        $declared = $observation->metadata['attributes'] ?? [];
        if (! is_array($declared)) {
            throw new RuntimeException('metadata.attributes deve ser um objeto.');
        }

        return $declared + ['source_observation_id' => $observation->observationId, 'source_revision' => $observation->revision, 'checksum' => $observation->checksum, 'method' => $this->method(), 'processed_at' => $processedAt, 'content_ref' => $pointer];
    }

    /** @return array<string, mixed> */
    private function scope(mixed $value, ?SourceObservation $observation = null): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        if (! is_array($value)) {
            throw new RuntimeException('Escopo persistido da fonte inválido.');
        }

        $declared = $observation?->metadata['scope'] ?? [];
        if (! is_array($declared)) {
            throw new RuntimeException('metadata.scope deve ser um objeto quando informado.');
        }
        foreach ($value as $dimension => $sourceValue) {
            if (isset($declared[$dimension]) && $declared[$dimension] !== $sourceValue) {
                throw new RuntimeException("metadata.scope não pode ampliar o escopo da fonte em {$dimension}.");
            }
        }

        return [...$value, ...$declared];
    }

    private function method(): string
    {
        return config('ingestion.extractor_name').'@'.config('ingestion.extractor_version');
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    private function event(SourceObservation $observation, SourceObservationResult $result, array $scope): array
    {
        $actor = ['actor_id' => $observation->command->actor->actorId, 'actor_type' => $observation->command->actor->actorType];

        return ['event_id' => (string) Str::uuid(), 'event_type' => 'KnowledgeRevisionCreated', 'schema_version' => '1.0.0', 'occurred_at' => now()->toISOString(), 'correlation_id' => $observation->command->correlationId ?? (string) Str::uuid(), 'actor' => $actor, 'scope' => $scope, 'entity' => ['entity_id' => $result->entityId, 'revision_id' => $result->entityRevisionId], 'payload' => ['source_observation_id' => $result->observationId, 'source_revision_id' => $result->sourceRevisionId, 'lifecycle_state' => 'observed', 'method' => $this->method(), 'confidence' => 1.0], 'command' => ['idempotency_key' => $observation->command->idempotencyKey, 'actor' => $actor]];
    }

    private function resultForObservation(string $observationId, bool $replayed): SourceObservationResult
    {
        $result = $this->database->table('executions')->where('observation_id', $observationId)->orderByDesc('created_at')->value('result');

        return $this->resultFromRecord($this->record($result), $replayed);
    }

    private function resultForSourceRevision(string $sourceRevisionId, bool $replayed): SourceObservationResult
    {
        $result = $this->database->table('executions')->where('revision_id', $sourceRevisionId)->orderByDesc('created_at')->value('result');

        return $this->resultFromRecord($this->record($result), $replayed);
    }

    /** @param array<string, mixed> $result */
    private function resultFromRecord(array $result, bool $replayed): SourceObservationResult
    {
        return new SourceObservationResult($this->string($result, 'observation_id'), $this->string($result, 'artifact_id'), $this->string($result, 'source_revision_id'), $this->string($result, 'entity_id'), $this->string($result, 'entity_revision_id'), $replayed);
    }

    /** @return array<string, mixed> */
    private function payload(SourceObservation $observation, ?string $safeContent = null): array
    {
        return ['schema_version' => $observation->schemaVersion, 'observation_id' => $observation->observationId, 'external_id' => $observation->externalId, 'source_type' => $observation->sourceType, 'uri' => $observation->uri, 'revision' => $observation->revision, 'observed_at' => $observation->observedAt, 'content' => $safeContent ?? $observation->content, 'content_ref' => $observation->contentRef === null ? null : ['uri' => $observation->contentRef->uri, 'checksum' => $observation->contentRef->checksum, 'media_type' => $observation->contentRef->mediaType], 'checksum' => $observation->checksum, 'metadata' => $observation->metadata, 'classification' => $observation->classification, 'command' => ['idempotency_key' => $observation->command->idempotencyKey, 'actor' => ['actor_id' => $observation->command->actor->actorId, 'actor_type' => $observation->command->actor->actorType], 'correlation_id' => $observation->command->correlationId]];
    }

    /** @return array<string, mixed> */
    private function record(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        }
        if (! is_array($value)) {
            throw new RuntimeException('Registro de ingestão persistido inválido.');
        }

        return $value;
    }

    /** @param array<string, mixed> $value */
    private function canonicalHash(array $value): string
    {
        return hash('sha256', json_encode($this->sortRecursively($value), JSON_THROW_ON_ERROR));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** @param array<string, mixed> $value */
    private function string(array $value, string $key): string
    {
        if (! isset($value[$key]) || ! is_string($value[$key])) {
            throw new RuntimeException("Resultado de ingestão sem {$key}.");
        }

        return $value[$key];
    }
}
