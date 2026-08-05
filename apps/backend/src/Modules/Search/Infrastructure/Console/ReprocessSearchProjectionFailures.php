<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Infrastructure\Console;

use Atlas\Modules\Search\Application\PublishedKnowledgeProjector;
use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Throwable;

final class ReprocessSearchProjectionFailures extends Command
{
    protected $signature = 'search:reprocess-dlq {--limit=100}';

    protected $description = 'Reprocessa falhas da projeção publicada de busca.';

    public function handle(DatabaseManager $database, PublishedKnowledgeProjector $projector): int
    {
        $rows = $database->table('search_projection_failures')->whereNull('resolved_at')->where('available_at', '<=', now())->orderBy('failed_at')->limit((int) $this->option('limit'))->get();
        foreach ($rows as $row) {
            try {
                $projector->project(json_decode((string) $row->event, true, 512, JSON_THROW_ON_ERROR), (string) config('search.alias'));
                $database->table('search_projection_failures')->where('event_id', $row->event_id)->update(['resolved_at' => now(), 'updated_at' => now()]);
            } catch (Throwable $exception) {
                $database->table('search_projection_failures')->where('event_id', $row->event_id)->update(['attempts' => (int) $row->attempts + 1, 'last_error' => mb_substr($exception->getMessage(), 0, 4000), 'failed_at' => now(), 'updated_at' => now()]);
            }
        }
        $this->info("Reprocessadas {$rows->count()} falhas de projeção.");

        return self::SUCCESS;
    }
}
