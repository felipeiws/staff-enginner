<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use Atlas\Contracts\Dto\Actor;
use Atlas\Contracts\Dto\AuditRecord;
use Atlas\Contracts\Dto\KnowledgeGapOccurrence;
use Atlas\Contracts\Port\AuditTrail;
use Atlas\Contracts\Port\KnowledgeGapRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Authoritative, auditable registry of unanswered questions. */
final readonly class KnowledgeGapService implements KnowledgeGapRecorder
{
    public function __construct(private DatabaseManager $database, private SemanticQuestionGrouper $grouper, private AuditTrail $auditTrail) {}

    public function record(KnowledgeGapOccurrence $occurrence): string
    {
        $now = now();
        $scope = $occurrence->scope;
        $scopeKey = hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
        $semanticKey = $this->grouper->key($occurrence->question);
        $windowStart = $now->copy()->subDays((int) config('knowledge_gaps.window_days', 30));

        return $this->database->transaction(function () use ($occurrence, $now, $scope, $scopeKey, $semanticKey, $windowStart): string {
            $gap = $this->database->table('knowledge_gaps')->where('semantic_key', $semanticKey)->where('scope_key', $scopeKey)->lockForUpdate()->first();
            if ($gap === null) {
                $gapId = (string) Str::uuid();
                $this->database->table('knowledge_gaps')->insert(['id' => $gapId, 'question' => $occurrence->question, 'semantic_key' => $semanticKey, 'scope' => json_encode($scope, JSON_THROW_ON_ERROR), 'scope_key' => $scopeKey, 'occurrence_count' => 1, 'first_occurred_at' => $now, 'window_started_at' => $now, 'last_occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
                $action = 'knowledge_gap.created';
            } else {
                $gapId = (string) $gap->id;
                $inWindow = Carbon::parse((string) $gap->last_occurred_at)->greaterThanOrEqualTo($windowStart);
                $this->database->table('knowledge_gaps')->where('id', $gapId)->update(['occurrence_count' => $inWindow ? ((int) $gap->occurrence_count + 1) : 1, 'window_started_at' => $inWindow ? $gap->window_started_at : $now, 'last_occurred_at' => $now, 'updated_at' => $now]);
                $action = 'knowledge_gap.occurred';
            }
            $this->database->table('knowledge_gap_occurrences')->insert(['id' => (string) Str::uuid(), 'knowledge_gap_id' => $gapId, 'question' => $occurrence->question, 'origin' => $occurrence->origin, 'method' => $occurrence->method, 'confidence' => $occurrence->confidence, 'correlation_id' => $occurrence->correlationId, 'occurred_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $this->auditTrail->record(new AuditRecord(new Actor('service:knowledge-gaps', 'service'), $action, 'knowledge_gap', $gapId, null, null, null, null, 'Pergunta sem resposta registrada como lacuna rastreada.', $occurrence->correlationId, $scope, null, ['origin' => $occurrence->origin, 'method' => $occurrence->method, 'confidence' => $occurrence->confidence], 'interno', $occurrence->method, $occurrence->confidence));

            return $gapId;
        });
    }

    /**
     * @param array<string,string> $scope
     * @return list<array<string,mixed>>
     */
    public function listed(array $scope): array
    {
        $gaps = $this->database->table('knowledge_gaps')->orderByDesc('occurrence_count')->orderByDesc('last_occurred_at')->get()->map(function (object $gap) use ($scope): ?array {
            $gapScope = json_decode((string) $gap->scope, true, 512, JSON_THROW_ON_ERROR);
            foreach ($scope as $key => $value) {
                if (($gapScope[$key] ?? null) !== $value) {
                    return null;
                }
            }
            $started = Carbon::parse((string) $gap->window_started_at);
            $ended = Carbon::parse((string) $gap->last_occurred_at);
            $count = (int) $gap->occurrence_count;
            return ['id' => (string) $gap->id, 'question' => (string) $gap->question, 'occurrence_count' => $count, 'period' => ['started_at' => $started->toISOString(), 'ended_at' => $ended->toISOString(), 'days' => max($started->diffInDays($ended) + 1, 1)], 'severity' => $this->severity($count), 'owner' => $gap->owner_id === null ? null : ['id' => (string) $gap->owner_id, 'type' => (string) $gap->owner_type], 'provenance' => ['method' => 'unanswered_question_v1', 'confidence' => 1.0]];
        })->filter()->all();

        return array_values($gaps);
    }

    /** @return array<string,mixed> */
    public function assign(string $gapId, string $ownerId, string $actorId, ?string $correlationId): array
    {
        return $this->database->transaction(function () use ($gapId, $ownerId, $actorId, $correlationId): array {
            $gap = $this->database->table('knowledge_gaps')->where('id', $gapId)->lockForUpdate()->first();
            if ($gap === null) {
                throw new \RuntimeException('Lacuna de conhecimento não encontrada.');
            }
            $now = now();
            $this->database->table('knowledge_gaps')->where('id', $gapId)->update(['owner_id' => $ownerId, 'owner_type' => 'human', 'assigned_at' => $now, 'assignment_method' => 'human_assigned', 'updated_at' => $now]);
            $task = $this->database->table('documentation_tasks')->where('knowledge_gap_id', $gapId)->where('status', 'open')->first();
            if ($task === null) {
                $taskId = (string) Str::uuid();
                $this->database->table('documentation_tasks')->insert(['id' => $taskId, 'knowledge_gap_id' => $gapId, 'owner_id' => $ownerId, 'owner_type' => 'human', 'status' => 'open', 'provenance' => json_encode(['origin' => 'knowledge_gap', 'gap_id' => $gapId, 'method' => 'human_assignment_v1', 'confidence' => 1.0, 'created_by' => $actorId], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now]);
            } else {
                $taskId = (string) $task->id;
                $this->database->table('documentation_tasks')->where('id', $taskId)->update(['owner_id' => $ownerId, 'updated_at' => $now]);
            }
            $scope = json_decode((string) $gap->scope, true, 512, JSON_THROW_ON_ERROR);
            $this->auditTrail->record(new AuditRecord(new Actor($actorId, 'human'), 'knowledge_gap.assigned', 'knowledge_gap', $gapId, null, null, null, 'assigned', 'Lacuna atribuída e tarefa de documentação aberta.', $correlationId, $scope, null, ['owner_id' => $ownerId, 'documentation_task_id' => $taskId], 'interno', 'human_assigned', 1.0));
            return ['gap_id' => $gapId, 'owner' => ['id' => $ownerId, 'type' => 'human'], 'documentation_task' => ['id' => $taskId, 'status' => 'open', 'provenance' => ['method' => 'human_assignment_v1', 'confidence' => 1.0]]];
        });
    }

    /** @return array{level:string,color:string} */
    private function severity(int $count): array
    {
        return $count >= (int) config('knowledge_gaps.danger_occurrences', 25) ? ['level' => 'crítica', 'color' => 'var(--danger)'] : ['level' => 'atenção', 'color' => 'var(--warn)'];
    }
}
