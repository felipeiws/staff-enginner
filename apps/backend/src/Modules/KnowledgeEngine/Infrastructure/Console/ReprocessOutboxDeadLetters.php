<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Console;

use Atlas\Modules\KnowledgeEngine\Application\OutboxDispatcher;
use Illuminate\Console\Command;

final class ReprocessOutboxDeadLetters extends Command
{
    protected $signature = 'outbox:reprocess-dlq {--limit= : Máximo de eventos a reprocessar}';

    protected $description = 'Recoloca eventos da DLQ da outbox na fila de publicação.';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $limit = $this->option('limit');
        $count = $dispatcher->reprocessDeadLetters($limit === null ? null : (int) $limit);
        $this->info("{$count} evento(s) recolocado(s) para publicação.");

        return self::SUCCESS;
    }
}
