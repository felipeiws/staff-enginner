<?php

declare(strict_types=1);

namespace App\Http\Api\Copilot;

use Atlas\Modules\Copilot\Application\KnowledgeRetriever;
use Atlas\Modules\Identity\Domain\Principal;
use Atlas\Modules\KnowledgeEngine\Application\GraphReadService;
use Atlas\Modules\KnowledgeEngine\Application\KnowledgeReadAccess;
use Atlas\Modules\Search\Application\HybridSearchService;
use Throwable;

final readonly class AuthorizedKnowledgeRetriever implements KnowledgeRetriever
{
    public function __construct(private HybridSearchService $search, private GraphReadService $graph) {}
    /**
     * @param array{id:string,grants:list<array{role?:string,scope:array<string,string>,permissions:list<string>,classification_ceiling:string}>} $actor
     * @param array{organization:string,application?:string,module?:string} $scope
     * @param list<float|int>|null $embedding
     * @return array{sources:list<array<string,mixed>>,entities:list<array<string,mixed>>,retrieval:array<string,mixed>}
     */
    public function retrieve(array $actor, string $question, array $scope, ?array $embedding): array
    {
        $principal = new Principal((string) $actor['id'], 'human', '', '', $actor['grants']);
        $result = $this->search->search($principal, $question, [...$scope, 'page_size' => 8, 'retrieval_limit' => 20], $embedding);
        $sources = $result['results'];
        $entities = [];
        if ($sources !== [] && ($sources[0]['entity_id'] ?? '') !== '') {
            try {
                $graph = $this->graph->neighborhood(new KnowledgeReadAccess($principal->grants), (string) $sources[0]['entity_id'], 1, 'type');
                $entities = array_values(array_map(fn(array $node): array => ['id' => $node['id'], 'type' => $node['type'], 'name' => $node['label'], 'color' => $node['color']], array_filter($graph['nodes'], fn(array $node): bool => $node['id'] !== $sources[0]['entity_id'])));
            } catch (Throwable) {
            }
        }
        return ['sources' => $sources, 'entities' => $entities, 'retrieval' => $result['retrieval']];
    }
}
