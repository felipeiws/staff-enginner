<?php

declare(strict_types=1);

namespace Atlas\Modules\Search\Infrastructure\Console;

use Atlas\Modules\Search\Application\PublishedKnowledgeProjector;
use Atlas\Modules\Search\Application\PublishedKnowledgeSchema;
use Atlas\Modules\Search\Domain\PublishedKnowledgeIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class ReindexPublishedKnowledge extends Command
{
    protected $signature = 'search:reindex-published';

    protected $description = 'Reconstrói a projeção de conhecimento publicado e troca o alias atomicamente.';

    public function handle(PublishedKnowledgeIndex $index, PublishedKnowledgeSchema $schema, PublishedKnowledgeProjector $projector): int
    {
        $collection = $schema->collectionName(Str::lower(Str::random(8)));
        $index->createCollection($collection, $schema->definition($collection));
        foreach ($projector->publishedDocuments() as $document) {
            $index->upsert($collection, $document);
        }
        $index->switchAlias($schema->alias(), $collection);
        $this->info("Alias {$schema->alias()} aponta para {$collection}.");

        return self::SUCCESS;
    }
}
