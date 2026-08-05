<?php

declare(strict_types=1);

use Atlas\Modules\KnowledgeEngine\Application\EventConsumer;
use Atlas\Modules\KnowledgeEngine\Application\IdempotentConsumerExecutor;
use Atlas\Modules\KnowledgeEngine\Application\OutboxDispatcher;
use Atlas\Modules\KnowledgeEngine\Infrastructure\OutboxEventStore;
use Atlas\Modules\KnowledgeEngine\Infrastructure\OutboxPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

final class RecordingOutboxPublisher implements OutboxPublisher
{
    /** @var list<string> */
    public array $published = [];

    public bool $fail = false;

    public function publish(array $event): void
    {
        if ($this->fail) {
            throw new RuntimeException('Redis indisponível');
        }
        $this->published[] = $event['event_id'];
    }
}

final class RecordingConsumer implements EventConsumer
{
    public int $calls = 0;

    public function name(): string
    {
        return 'search-projection';
    }

    public function consume(array $event): void
    {
        $this->calls++;
    }
}

function outboxUuid(int $number): string
{
    return sprintf('50000000-0000-4000-8000-%012d', $number);
}

/** @return array<string, mixed> */
function domainEvent(int $number, string $aggregateId): array
{
    $actor = ['actor_id' => 'worker:ingestion', 'actor_type' => 'worker'];

    return [
        'event_id' => outboxUuid($number), 'event_type' => 'KnowledgeRevision.Created', 'schema_version' => '1.0.0',
        'occurred_at' => now()->toISOString(), 'correlation_id' => outboxUuid(900), 'actor' => $actor,
        'scope' => ['application' => 'atlas'], 'entity' => ['entity_id' => $aggregateId],
        'payload' => ['source_revision' => 'git:abc123', 'method' => 'parser@1.0.0', 'confidence' => 0.9],
        'command' => ['idempotency_key' => "command-{$number}", 'actor' => $actor],
    ];
}

function appendOutboxEvent(int $number, ?string $aggregateId = null): void
{
    DB::transaction(function () use ($number, $aggregateId): void {
        app(OutboxEventStore::class)->append('knowledge_revision', domainEvent($number, $aggregateId ?? outboxUuid(100 + $number)));
    });
}

beforeEach(function (): void {
    config()->set('outbox.base_backoff_seconds', 1);
    config()->set('outbox.max_attempts', 2);
    $this->publisher = new RecordingOutboxPublisher;
    app()->instance(OutboxPublisher::class, $this->publisher);
});

it('persists the validated envelope in the same domain transaction', function (): void {
    expect(Schema::hasTable('outbox_events'))->toBeTrue();
    expect(Schema::hasTable('processed_events'))->toBeTrue();
    appendOutboxEvent(1);
    expect(DB::table('outbox_events')->where('event_id', outboxUuid(1))->value('status'))->toBe('pending');
});

it('claims each event only once when dispatchers race', function (): void {
    appendOutboxEvent(1);
    $dispatcher = app(OutboxDispatcher::class);

    expect($dispatcher->dispatch())->toMatchArray(['delivered' => 1, 'retried' => 0, 'dead_lettered' => 0]);
    expect($dispatcher->dispatch())->toMatchArray(['delivered' => 0, 'retried' => 0, 'dead_lettered' => 0]);
    expect($this->publisher->published)->toBe([outboxUuid(1)]);
});

it('retries publication failures with backoff and moves exhausted events to the DLQ', function (): void {
    appendOutboxEvent(1);
    $this->publisher->fail = true;
    $dispatcher = app(OutboxDispatcher::class);

    expect($dispatcher->dispatch()['retried'])->toBe(1);
    $row = DB::table('outbox_events')->where('event_id', outboxUuid(1))->first();
    expect($row->status)->toBe('pending')->and($row->tentativas)->toBe(1);

    DB::table('outbox_events')->where('event_id', outboxUuid(1))->update(['available_at' => now()->subSecond()]);
    expect($dispatcher->dispatch()['dead_lettered'])->toBe(1);
    expect(DB::table('outbox_events')->where('event_id', outboxUuid(1))->value('status'))->toBe('dead_letter');

    expect($dispatcher->reprocessDeadLetters())->toBe(1);
    $this->publisher->fail = false;
    expect($dispatcher->dispatch()['delivered'])->toBe(1);
});

it('preserves aggregate ordering after a failed earlier event', function (): void {
    $aggregate = outboxUuid(300);
    appendOutboxEvent(1, $aggregate);
    appendOutboxEvent(2, $aggregate);
    $this->publisher->fail = true;
    $dispatcher = app(OutboxDispatcher::class);
    $dispatcher->dispatch();

    $this->publisher->fail = false;
    expect($dispatcher->dispatch()['delivered'])->toBe(0);
    expect($this->publisher->published)->toBe([]);

    DB::table('outbox_events')->where('event_id', outboxUuid(1))->update(['available_at' => now()->subSecond()]);
    expect($dispatcher->dispatch()['delivered'])->toBe(1);
    expect($dispatcher->dispatch()['delivered'])->toBe(1);
    expect($this->publisher->published)->toBe([outboxUuid(1), outboxUuid(2)]);
});

it('does not execute a consumer twice for duplicated delivery', function (): void {
    $consumer = new RecordingConsumer;
    $executor = app(IdempotentConsumerExecutor::class);
    $event = domainEvent(1, outboxUuid(101));

    expect($executor->execute($consumer, $event))->toBeTrue();
    expect($executor->execute($consumer, $event))->toBeFalse();
    expect($consumer->calls)->toBe(1);
    expect(DB::table('processed_events')->count())->toBe(1);
});
