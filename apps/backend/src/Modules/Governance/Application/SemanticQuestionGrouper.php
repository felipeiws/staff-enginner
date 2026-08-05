<?php

declare(strict_types=1);

namespace Atlas\Modules\Governance\Application;

/**
 * Conservative, explainable semantic grouping for Portuguese questions.
 * It intentionally never sends unanswered questions to a model or provider.
 */
final class SemanticQuestionGrouper
{
    public function key(string $question): string
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = strtr($normalized, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
        $tokens = preg_split('/[^a-z0-9]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopwords = array_flip(['a', 'ao', 'as', 'como', 'da', 'das', 'de', 'do', 'dos', 'e', 'em', 'eu', 'faz', 'faco', 'feito', 'o', 'os', 'para', 'por', 'qual', 'quem', 'se', 'um', 'uma']);
        $synonyms = ['estornar' => 'estorno', 'estorne' => 'estorno', 'funciona' => 'funcionamento', 'funcionar' => 'funcionamento'];
        $tokens = array_values(array_filter(array_map(static fn(string $token): string => $synonyms[$token] ?? $token, $tokens), static fn(string $token): bool => ! isset($stopwords[$token])));
        sort($tokens, SORT_STRING);

        return hash('sha256', implode(' ', array_unique($tokens)));
    }
}
