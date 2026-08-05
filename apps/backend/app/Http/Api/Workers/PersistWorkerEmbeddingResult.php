<?php

declare(strict_types=1);

namespace App\Http\Api\Workers;

use Atlas\Modules\Identity\Application\AuthorizationService;
use Atlas\Modules\Identity\Domain\AuthorizationRequest;
use Atlas\Modules\Identity\Domain\Principal;
use Atlas\Modules\Search\Application\EmbeddingPersistenceService;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;

/** HTTP orchestration across identity and search; the Search module receives only authorized results. */
final readonly class PersistWorkerEmbeddingResult
{
    public function __construct(
        private DatabaseManager $database,
        private AuthorizationService $authorization,
        private EmbeddingPersistenceService $embeddings,
    ) {}

    /** @param array{revision_id:string,chunks:list<array{ordinal:int,start_offset:int,end_offset:int,text:string,tokens:int,checksum:string,embedding:list<float|int>,model:string,model_version:string,parameters:array<string,mixed>,cost?:float|int|null,cost_currency?:string|null}>} $result */
    public function persist(Principal $principal, array $result): void
    {
        $source = $this->database->table('revisions as r')
            ->join('artifacts as a', 'a.id', '=', 'r.artifact_id')
            ->join('sources as s', 's.id', '=', 'a.source_id')
            ->where('r.id', $result['revision_id'])
            ->select(['s.scope', 's.classification'])
            ->first();
        if ($source === null) {
            throw new InvalidArgumentException('A revisão de origem não existe.');
        }
        $scope = json_decode((string) $source->scope, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($scope) || ! isset($scope['organization']) || ! is_string($scope['organization'])) {
            throw new InvalidArgumentException('O escopo da revisão de origem é inválido.');
        }
        /** @var array{organization:string, application?:string, module?:string} $scope */
        $this->authorization->assertAuthorized($principal, new AuthorizationRequest('knowledge.contribute', $scope, (string) $source->classification));
        $this->embeddings->persistGenerated($result);
    }
}
