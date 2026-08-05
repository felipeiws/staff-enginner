<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Application;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Applies the versioned worker result; workers never write the database directly. */
final readonly class EmbeddingPersistenceService
{
    public function __construct(private DatabaseManager $database) {}

    /** @param array{chunk_id:string,model:string,model_version:string,parameters:array<string,mixed>,chunk_checksum:string,embedding:list<float|int>,cost?:float|int|null,cost_currency?:string|null,execution_id?:string|null} $result */
    public function persist(array $result): void
    {
        $dimensions = count($result['embedding']);
        if ($dimensions !== (int) config('search.embedding_dimensions')) {
            throw new InvalidArgumentException('A dimensão do embedding não corresponde ao índice configurado.');
        }
        $chunkChecksum = (string) $this->database->table('chunks')->where('id', $result['chunk_id'])->value('checksum');
        if ($chunkChecksum === '' || ! hash_equals($chunkChecksum, $result['chunk_checksum'])) {
            throw new InvalidArgumentException('O checksum do chunk não corresponde à origem persistida.');
        }

        $values = [
            'model' => $result['model'], 'model_version' => $result['model_version'],
            'parameters' => json_encode($result['parameters'], JSON_THROW_ON_ERROR), 'chunk_checksum' => $result['chunk_checksum'],
            'embedding' => $this->database->getDriverName() === 'pgsql' ? '[' . implode(',', array_map(static fn($value): string => (string) (float) $value, $result['embedding'])) . ']' : json_encode($result['embedding'], JSON_THROW_ON_ERROR),
            'dimensions' => $dimensions, 'cost' => $result['cost'] ?? null, 'cost_currency' => $result['cost_currency'] ?? null,
            'execution_id' => $result['execution_id'] ?? null, 'updated_at' => now(),
        ];
        $this->database->table('chunk_embeddings')->updateOrInsert(
            ['chunk_id' => $result['chunk_id'], 'model' => $result['model'], 'model_version' => $result['model_version']],
            ['id' => (string) Str::uuid(), ...$values, 'created_at' => now()],
        );
    }

    /**
     * Consumes the worker's EmbeddingGenerated contract atomically. Chunk IDs stay internal;
     * the worker identifies a chunk by its immutable revision and ordinal.
     *
     * @param array{revision_id:string,chunks:list<array{ordinal:int,start_offset:int,end_offset:int,text:string,tokens:int,checksum:string,embedding:list<float|int>,model:string,model_version:string,parameters:array<string,mixed>,cost?:float|int|null,cost_currency?:string|null}>} $event
     */
    public function persistGenerated(array $event): void
    {
        $this->database->transaction(function () use ($event): void {
            foreach ($event['chunks'] as $generated) {
                $chunk = $this->database->table('chunks')->where('revision_id', $event['revision_id'])->where('ordinal', $generated['ordinal'])->first();
                if ($chunk === null) {
                    $chunkId = (string) Str::uuid();
                    $this->database->table('chunks')->insert([
                        'id' => $chunkId, 'revision_id' => $event['revision_id'], 'ordinal' => $generated['ordinal'],
                        'start_offset' => $generated['start_offset'], 'end_offset' => $generated['end_offset'], 'text' => $generated['text'],
                        'tokens' => $generated['tokens'], 'checksum' => $generated['checksum'], 'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    if (! hash_equals((string) $chunk->checksum, $generated['checksum'])) {
                        throw new InvalidArgumentException('O chunk existente diverge do resultado determinístico do worker.');
                    }
                    $chunkId = (string) $chunk->id;
                }
                $this->persist(['chunk_id' => $chunkId, ...$generated, 'chunk_checksum' => $generated['checksum']]);
            }
        });
    }
}
