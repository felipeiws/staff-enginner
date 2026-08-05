<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

use App\Http\Api\Problem\DomainProblem;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;

/** Authoritative approval projection; proposals remain candidates until a human decision. */
final class ApprovalReadService
{
    private const CLASSIFICATIONS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    public function __construct(private readonly DatabaseManager $database) {}

    /** @return array{approvals:list<array<string,mixed>>,low_impact_auto_approval:array{count:int,hours:int}} */
    public function pending(ApprovalAccess $access): array
    {
        $visible = [];
        $lowImpact = [];
        foreach ($this->database->table('approvals')->whereNull('decision')->orderByDesc('proposed_at')->get() as $approval) {
            $row = $this->approvalRow((string) $approval->id);
            if ($row === null || ! $this->allowed($access, $row, 'knowledge.read')) {
                continue;
            }
            if ($this->autoApprovalHours($row['proposal']) !== null && $this->impactLevel($row['proposal']) === 'baixo') {
                $lowImpact[] = $row;

                continue;
            }
            $visible[] = $this->summary($row);
        }

        return ['approvals' => $visible, 'low_impact_auto_approval' => ['count' => count($lowImpact), 'hours' => $lowImpact === [] ? 48 : (int) $this->autoApprovalHours($lowImpact[0]['proposal'])]];
    }

    /** @return array<string,mixed> */
    public function detail(ApprovalAccess $access, string $approvalId): array
    {
        $row = $this->requireApproval($approvalId);
        $this->assertAllowed($access, $row, 'knowledge.read');
        $proposal = $row['proposal'];

        return [
            ...$this->summary($row),
            'analysis' => ['text' => (string) ($proposal['ai_analysis']['text'] ?? $proposal['ai_analysis']['summary'] ?? ''), 'model' => $proposal['ai_analysis']['model'] ?? null, 'confidence' => (float) ($proposal['ai_analysis']['confidence'] ?? $proposal['confidence'] ?? $row['confidence'])],
            'diff' => ['current' => (string) ($proposal['current_text'] ?? $row['body'] ?? ''), 'proposed' => (string) ($proposal['proposed_text'] ?? '')],
            'impact' => ['level' => $this->impactLevel($proposal), 'entities' => array_values(array_filter($proposal['impacted_entities'] ?? [], 'is_string'))],
            'provenance' => $this->provenance($row),
        ];
    }

    /** @return array<string,mixed> */
    public function commandContext(ApprovalAccess $access, string $approvalId): array
    {
        $row = $this->requireApproval($approvalId);
        $this->assertAllowed($access, $row, 'knowledge.approve');

        return $row;
    }

    /** @return array<string,mixed> */
    private function requireApproval(string $approvalId): array
    {
        $row = $this->approvalRow($approvalId);
        if ($row === null) {
            throw new DomainProblem(404, 'approval.not_found', 'Proposta de aprovação não encontrada.');
        }

        return $row;
    }

    /** @return array<string,mixed>|null */
    private function approvalRow(string $approvalId): ?array
    {
        $approval = $this->database->table('approvals')->where('id', $approvalId)->first();
        if ($approval === null) {
            return null;
        }
        $revisionId = $approval->entity_revision_id;
        if ($revisionId === null && $approval->assertion_id !== null) {
            $revisionId = $this->database->table('assertions')->where('id', $approval->assertion_id)->value('subject_entity_revision_id');
        }
        $revision = $revisionId === null ? null : $this->database->table('entity_revisions as er')->join('entities as e', 'e.id', '=', 'er.entity_id')->leftJoin('revisions as r', 'r.id', '=', 'er.source_revision_id')->leftJoin('artifacts as a', 'a.id', '=', 'r.artifact_id')->leftJoin('sources as s', 's.id', '=', 'a.source_id')->where('er.id', $revisionId)->select(['er.id as revision_id', 'er.title', 'er.body', 'er.attributes', 'er.confidence', 'e.entity_type', 'e.scope', 'r.source_revision', 'r.observed_at', 'a.uri as origin_uri', 's.classification as source_classification'])->first();
        if ($revision === null) {
            return null;
        }
        $proposal = json_decode((string) $approval->proposal, true, flags: JSON_THROW_ON_ERROR);
        $attributes = json_decode((string) $revision->attributes, true, flags: JSON_THROW_ON_ERROR);
        $scope = json_decode((string) $revision->scope, true, flags: JSON_THROW_ON_ERROR);

        return ['id' => (string) $approval->id, 'subject_kind' => (string) $approval->subject_kind, 'decision' => $approval->decision, 'proposed_by_id' => (string) $approval->proposed_by_id, 'proposed_by_type' => (string) $approval->proposed_by_type, 'proposed_at' => (string) $approval->proposed_at, 'external_pr_reference' => $approval->external_pr_reference, 'proposal' => $proposal, 'revision_id' => (string) $revision->revision_id, 'title' => (string) $revision->title, 'body' => $revision->body, 'entity_type' => (string) $revision->entity_type, 'scope' => $scope, 'attributes' => $attributes, 'confidence' => (float) $revision->confidence, 'source_revision' => $revision->source_revision, 'observed_at' => $revision->observed_at, 'origin_uri' => $revision->origin_uri, 'classification' => (string) ($attributes['classification'] ?? $revision->source_classification ?? 'interno')];
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function summary(array $row): array
    {
        $proposal = $row['proposal'];
        $origin = is_array($proposal['origin'] ?? null) ? $proposal['origin'] : [];

        return ['id' => $row['id'], 'title' => $row['title'], 'entity_type' => $row['entity_type'], 'entity_type_color' => $this->typeColor($row['entity_type']), 'author' => ['id' => $row['proposed_by_id'], 'type' => $row['proposed_by_type']], 'origin' => ['kind' => $origin['kind'] ?? null, 'revision' => $origin['revision'] ?? $row['source_revision'], 'detected_by' => $origin['detected_by'] ?? null], 'age' => $this->age($row['proposed_at']), 'proposed_at' => $row['proposed_at'], 'provenance' => $this->provenance($row)];
    }

    /** @param array<string,mixed> $row
     * @return array<string,mixed> */
    private function provenance(array $row): array
    {
        $proposal = $row['proposal'];

        return ['origin' => $row['origin_uri'], 'revision' => $row['source_revision'], 'method' => $proposal['method'] ?? 'human_review', 'confidence' => (float) ($proposal['confidence'] ?? $row['confidence']), 'processed_at' => $proposal['processed_at'] ?? $row['observed_at']];
    }

    /** @param array<string,mixed> $row */
    private function assertAllowed(ApprovalAccess $access, array $row, string $permission): void
    {
        if (! $this->allowed($access, $row, $permission)) {
            throw new ApprovalAccessDenied('Acesso negado para este escopo ou classificação.');
        }
    }

    /** @param array<string,mixed> $row */
    private function allowed(ApprovalAccess $access, array $row, string $permission): bool
    {
        foreach ($access->grants as $grant) {
            if ((! in_array($permission, $grant['permissions'], true) && ! in_array('*', $grant['permissions'], true)) || (self::CLASSIFICATIONS[$grant['classification_ceiling']] ?? -1) < (self::CLASSIFICATIONS[$row['classification']] ?? PHP_INT_MAX) || ! isset($grant['scope']['organization'])) {
                continue;
            }
            $matches = true;
            foreach (['organization', 'application', 'module'] as $dimension) {
                if (isset($grant['scope'][$dimension]) && ($row['scope'][$dimension] ?? null) !== $grant['scope'][$dimension]) {
                    $matches = false;
                }
            }
            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,mixed> $proposal */
    private function autoApprovalHours(array $proposal): ?int
    {
        $hours = $proposal['auto_approve_after_hours'] ?? ($proposal['policy']['auto_approve_after_hours'] ?? null);

        return is_int($hours) && $hours > 0 ? $hours : null;
    }

    /** @param array<string,mixed> $proposal */
    private function impactLevel(array $proposal): string
    {
        return (string) ($proposal['impact_level'] ?? $proposal['impact']['level'] ?? 'normal');
    }

    private function typeColor(string $type): string
    {
        return match ($type) {
            'Processo' => 'violet', 'Regra' => 'warn', 'FAQ' => 'sky', 'Tela' => 'info', default => 'acc',
        };
    }

    private function age(string $proposedAt): string
    {
        $hours = (int) floor(Carbon::parse($proposedAt)->diffInHours(now()));

        return $hours < 1 ? 'há poucos minutos' : ($hours < 24 ? "há {$hours}h" : 'há '.intdiv($hours, 24).'d');
    }
}
