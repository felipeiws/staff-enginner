<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

/**
 * Deterministic security gate at the ingestion boundary. It deliberately returns
 * redacted content only: callers must never persist or log a detected secret.
 */
final class ContentClassificationGate
{
    private const LEVELS = ['publico' => 0, 'interno' => 1, 'restrito' => 2, 'confidencial' => 3];

    /** @param array<string,mixed> $metadata
     * @return array{classification:string,method:string,confidence:float,contains_personal_data:bool,quarantined:bool,findings:list<array{kind:string,fingerprint:string}>,redacted_content:?string,source_classification:string,override:array<string,mixed>|null} */
    public function assess(?string $content, string $sourceClassification, array $metadata): array
    {
        $source = $this->normalise($sourceClassification);
        $findings = [];
        $redacted = $content;
        foreach ([
            'api_key' => '/\b(?:sk|pk)_[A-Za-z0-9_-]{16,}\b|\bAKIA[0-9A-Z]{16}\b/',
            'bearer_token' => '/\bBearer\s+[A-Za-z0-9._~+\/-]{16,}\b/i',
            'connection_string' => '/\b(?:postgres(?:ql)?|mysql|mongodb(?:\+srv)?|redis):\/\/[^\s"\']+/i',
            'config_credential' => '/\b(?:API[_-]?KEY|SECRET|PASSWORD|TOKEN)\s*[=:]\s*["\']?[^\s"\']{8,}/i',
        ] as $kind => $pattern) {
            if ($content !== null && preg_match_all($pattern, $content, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $findings[] = ['kind' => $kind, 'fingerprint' => hash('sha256', $match)];
                }
                $redacted = preg_replace($pattern, '[SEGREDO_REDACTED]', (string) $redacted) ?? $redacted;
            }
        }
        $containsPersonalData = $content !== null && (preg_match('/\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/u', $content) === 1 || preg_match('/\b\d{3}[.\s-]?\d{3}[.\s-]?\d{3}-?\d{2}\b/u', $content) === 1);
        $override = is_array($metadata['classification_override'] ?? null) ? $metadata['classification_override'] : null;
        $classification = $source;
        $method = 'source_classification';
        $confidence = 1.0;
        if ($containsPersonalData && self::LEVELS[$classification] < self::LEVELS['restrito']) {
            $classification = 'restrito'; $method = 'personal_data_rule_v1'; $confidence = .95;
        }
        if ($findings !== []) {
            $classification = 'confidencial'; $method = 'secret_detection_rule_v1'; $confidence = .99;
        }
        if ($override !== null && isset($override['level'], self::LEVELS[(string) $override['level']]) && is_string($override['reason'] ?? null) && trim($override['reason']) !== '') {
            $classification = (string) $override['level']; $method = 'manual_override'; $confidence = 1.0;
        }

        return ['classification' => $classification, 'method' => $method, 'confidence' => $confidence, 'contains_personal_data' => $containsPersonalData, 'quarantined' => $findings !== [], 'findings' => $findings, 'redacted_content' => $redacted, 'source_classification' => $source, 'override' => $override];
    }

    private function normalise(string $classification): string
    {
        $classification = match ($classification) { 'public', 'publico' => 'publico', 'internal', 'interno' => 'interno', 'restricted', 'restrito' => 'restrito', 'confidential', 'confidencial' => 'confidencial', default => 'interno' };
        return $classification;
    }
}
