<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Application;

use Atlas\Modules\KnowledgeEngine\Infrastructure\OutboxPublisher;
use Illuminate\Database\DatabaseManager;
use Throwable;

final readonly class OutboxDispatcher
{
    public function __construct(
        private DatabaseManager $database,
        private OutboxPublisher $publisher,
    ) {}

    /** @return array{delivered: int, retried: int, dead_lettered: int} */
    public function dispatch(?int $batchSize = null): array
    {
        $this->releaseExpiredClaims();
        $result = ['delivered' => 0, 'retried' => 0, 'dead_lettered' => 0];

        foreach ($this->claimBatch($batchSize ?? (int) config('outbox.batch_size')) as $row) {
            try {
                /** @var array<string, mixed> $event */
                $event = json_decode((string) data_get($row, 'payload'), true, 512, JSON_THROW_ON_ERROR);
                $this->publisher->publish($event);
                $this->database->table('outbox_events')->where('event_id', (string) data_get($row, 'event_id'))->update([
                    'status' => 'delivered', 'locked_at' => null, 'last_error' => null, 'updated_at' => now(),
                ]);
                $result['delivered']++;
            } catch (Throwable $exception) {
                $attempts = (int) data_get($row, 'tentativas') + 1;
                $deadLettered = $attempts >= (int) config('outbox.max_attempts');
                $this->database->table('outbox_events')->where('event_id', (string) data_get($row, 'event_id'))->update([
                    'status' => $deadLettered ? 'dead_letter' : 'pending',
                    'tentativas' => $attempts,
                    'available_at' => $deadLettered ? now() : now()->addSeconds($this->backoff($attempts)),
                    'locked_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 4000),
                    'updated_at' => now(),
                ]);
                $result[$deadLettered ? 'dead_lettered' : 'retried']++;
            }
        }

        return $result;
    }

    public function reprocessDeadLetters(?int $limit = null): int
    {
        return $this->database->table('outbox_events')->where('status', 'dead_letter')
            ->orderBy('occurred_at')->limit($limit ?? PHP_INT_MAX)->update([
                'status' => 'pending', 'tentativas' => 0, 'available_at' => now(), 'locked_at' => null, 'last_error' => null, 'updated_at' => now(),
            ]);
    }

    /** @return list<object> */
    private function claimBatch(int $limit): array
    {
        return $this->database->transaction(function () use ($limit): array {
            $rows = $this->database->table('outbox_events')
                ->whereIn('status', ['pending', 'processing'])
                ->orderBy('occurred_at')->orderBy('event_id')->lockForUpdate()->get();
            $claimed = [];
            $aggregates = [];

            foreach ($rows as $row) {
                $key = (string) data_get($row, 'aggregate').'|'.(string) data_get($row, 'aggregate_id');
                if (isset($aggregates[$key])) {
                    continue;
                }
                $aggregates[$key] = true;
                if (data_get($row, 'status') !== 'pending' || data_get($row, 'available_at') > now()) {
                    continue;
                }
                $this->database->table('outbox_events')->where('event_id', (string) data_get($row, 'event_id'))->update([
                    'status' => 'processing', 'locked_at' => now(), 'updated_at' => now(),
                ]);
                $claimed[] = $row;
                if (count($claimed) === $limit) {
                    break;
                }
            }

            return $claimed;
        });
    }

    private function releaseExpiredClaims(): void
    {
        $timeout = now()->subSeconds((int) config('outbox.processing_timeout_seconds'));
        $this->database->table('outbox_events')->where('status', 'processing')->where('locked_at', '<=', $timeout)->update([
            'status' => 'pending', 'locked_at' => null, 'available_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function backoff(int $attempt): int
    {
        return min(3600, (int) config('outbox.base_backoff_seconds') * (2 ** ($attempt - 1)));
    }
}
