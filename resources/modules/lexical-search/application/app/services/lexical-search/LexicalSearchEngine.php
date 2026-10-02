<?php

namespace GFrame\Modules\LexicalSearch\Services;

class LexicalSearchEngine
{
    private const DEFAULT_STOP_WORDS = [
        'de', 'del', 'la', 'las', 'el', 'los', 'en', 'por', 'para',
        'con', 'sin', 'un', 'una', 'unos', 'unas', 'al', 'y', 'o',
    ];

    public function rank(array $rows, string $query, array $fields, array $options = []): array
    {
        $query = trim((string)preg_replace('/\s+/u', ' ', $query));
        $stopWords = array_values(array_unique(array_merge(
            self::DEFAULT_STOP_WORDS,
            (array)($options['stop_words'] ?? [])
        )));
        $tokens = $this->tokenize($query, $stopWords, (int)($options['max_tokens'] ?? 10));
        $normalizedQuery = $this->normalize($query);
        $threshold = max(0.0, (float)($options['threshold'] ?? 0.18));
        $phraseWeight = max(0.0, (float)($options['phrase_weight'] ?? 0.08));
        $combinedWeight = max(0.0, (float)($options['combined_weight'] ?? 0.14));
        $filter = $options['filter'] ?? null;
        $boost = $options['boost'] ?? null;
        $snippetFields = (array)($options['snippet_fields'] ?? []);
        $ranked = [];

        foreach ($rows as $row) {
            $row = is_object($row) ? (array)$row : (array)$row;
            if (is_callable($filter) && !$filter($row)) {
                continue;
            }

            $combinedParts = [];
            $score = 0.0;
            foreach ($fields as $field => $weight) {
                $value = $this->fieldText($row, (string)$field);
                $normalized = $this->normalize($value);
                if ($normalized !== '') {
                    $combinedParts[] = $normalized;
                }
                $score += max(0.0, (float)$weight) * $this->tokenScore($tokens, $normalized);
            }

            $combined = trim(implode(' ', $combinedParts));
            $keywordScore = $this->tokenScore($tokens, $combined);
            $phraseScore = $normalizedQuery !== '' && str_contains($combined, $normalizedQuery) ? 1.0 : 0.0;
            $score += ($combinedWeight * $keywordScore) + ($phraseWeight * $phraseScore);
            if (is_callable($boost)) {
                $score += max(0.0, (float)$boost($row));
            }
            if ($score < $threshold) {
                continue;
            }

            $row['_search_score'] = $score;
            if ($snippetFields !== []) {
                $row['snippet'] = $this->snippet($row, $snippetFields, $normalizedQuery, $tokens);
            }
            $ranked[] = $row;
        }

        usort($ranked, static function (array $left, array $right): int {
            return ((float)($right['_search_score'] ?? 0)) <=> ((float)($left['_search_score'] ?? 0));
        });
        return $ranked;
    }

    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?: $text;
        return trim((string)(preg_replace('/\s+/u', ' ', $text) ?: $text));
    }

    private function tokenize(string $query, array $stopWords, int $maxTokens): array
    {
        $parts = preg_split('/\s+/u', $this->normalize($query)) ?: [];
        return array_slice(array_values(array_unique(array_filter($parts, static function (string $token) use ($stopWords): bool {
            return mb_strlen($token, 'UTF-8') >= 2 && !in_array($token, $stopWords, true);
        }))), 0, max(1, $maxTokens));
    }

    private function tokenScore(array $tokens, string $haystack): float
    {
        if ($tokens === [] || $haystack === '') {
            return 0.0;
        }

        $words = preg_split('/\s+/u', $haystack) ?: [];
        $score = 0.0;
        foreach ($tokens as $token) {
            if (str_contains($haystack, $token)) {
                $score += 1.0;
                continue;
            }
            if (mb_strlen($token, 'UTF-8') < 4) {
                continue;
            }

            $best = 0.0;
            foreach ($words as $word) {
                if ($word === '') {
                    continue;
                }
                if (str_starts_with($word, $token) || str_starts_with($token, $word)) {
                    $best = max($best, 0.82);
                    continue;
                }
                $limit = mb_strlen($token, 'UTF-8') >= 7 ? 2 : 1;
                if (abs(strlen($word) - strlen($token)) <= $limit && levenshtein($token, $word) <= $limit) {
                    $best = max($best, 0.68);
                }
            }
            $score += $best;
        }
        return min(1.0, $score / count($tokens));
    }

    private function snippet(array $row, array $fields, string $normalizedQuery, array $tokens): string
    {
        $parts = [];
        foreach ($fields as $field) {
            $value = trim(strip_tags($this->fieldText($row, (string)$field)));
            if ($value !== '') {
                $parts[] = $value;
            }
        }
        $plain = trim((string)(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?: ''));
        if ($plain === '') {
            return '';
        }

        $normalized = $this->normalize($plain);
        $position = $normalizedQuery !== '' ? mb_strpos($normalized, $normalizedQuery, 0, 'UTF-8') : false;
        if ($position === false) {
            foreach ($tokens as $token) {
                $position = mb_strpos($normalized, $token, 0, 'UTF-8');
                if ($position !== false) {
                    break;
                }
            }
        }

        $start = $position === false ? 0 : max(0, (int)$position - 50);
        $snippet = trim(mb_substr($plain, $start, 240, 'UTF-8'));
        if ($start > 0) {
            $snippet = '…' . ltrim($snippet);
        }
        if (($start + 240) < mb_strlen($plain, 'UTF-8')) {
            $snippet = rtrim($snippet) . '…';
        }
        return $snippet;
    }

    private function fieldText(array $row, string $field): string
    {
        $value = $row[$field] ?? '';
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '';
        }
        return (string)$value;
    }
}
