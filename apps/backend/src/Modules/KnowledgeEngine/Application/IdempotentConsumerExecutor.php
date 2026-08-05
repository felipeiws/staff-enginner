<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

final readonly class IdempotentConsumerExecutor
{
    public function __construct(private DatabaseManager $database) {}

    /**
     * @param  array<string, mixed>  $event
     *                                       Returns false when this event was already processed by this consumer.
     */
    public function execute(EventConsumer $consumer, array $event): bool
    {
        /** @var string $eventId */
        $eventId = $event['event_id'] ?? '';

        return $this->database->transaction(function () use ($consumer, $event, $eventId): bool {
            try {
                $inserted = $this->database->table('processed_events')->insertOrIgnore([
                    'consumer' => $consumer->name(),
                    'event_id' => $eventId,
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (QueryException) {
                return false;
            }

            if ($inserted !== 1) {
                return false;
            }

            $consumer->consume($event);

            return true;
        });
    }
}
