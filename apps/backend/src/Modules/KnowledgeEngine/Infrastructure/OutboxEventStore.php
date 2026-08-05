<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure;

use Atlas\Contracts\Factory\ContractFactory;
use Illuminate\Database\DatabaseManager;
use LogicException;

final readonly class OutboxEventStore
{
    public function __construct(
        private DatabaseManager $database,
        private ContractFactory $contracts,
    ) {}

    /**
     * Persists an event in the transaction that changes the aggregate.
     *
     * @param  array<string, mixed>  $event
     */
    public function append(string $aggregate, array $event): void
    {
        if ($this->database->transactionLevel() === 0) {
            throw new LogicException('Outbox events must be appended inside the domain transaction.');
        }

        $validated = $this->contracts->domainEvent($event);
        $now = now();

        $this->database->table('outbox_events')->insert([
            'event_id' => $validated->eventId,
            'aggregate' => $aggregate,
            'aggregate_id' => $validated->entity->entityId,
            'tipo' => $validated->eventType,
            'schema_version' => $validated->schemaVersion,
            'payload' => json_encode($event, JSON_THROW_ON_ERROR),
            'occurred_at' => $validated->occurredAt,
            'correlation_id' => $validated->correlationId,
            'ator' => json_encode([
                'actor_id' => $validated->actor->actorId,
                'actor_type' => $validated->actor->actorType,
            ], JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'tentativas' => 0,
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
