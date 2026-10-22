<?php

namespace App\Support;

class DiffFormatter
{
    /**
     * Format a word-level diff payload between original and proposed text.
     *
     * @return array{
     *     original: string,
     *     proposed: string,
     *     has_changes: bool,
     *     chunks: array<int, array{type: 'common'|'added'|'removed', value: string}>,
     *     stats: array{additions: int, deletions: int, unchanged: int}
     * }
     */
    public static function format(string $original, string $proposed): array
    {
        if ($original === $proposed) {
            return [
                'original' => $original,
                'proposed' => $proposed,
                'has_changes' => false,
                'chunks' => $original === '' ? [] : [['type' => 'common', 'value' => $original]],
                'stats' => [
                    'additions' => 0,
                    'deletions' => 0,
                    'unchanged' => mb_strlen($original),
                ],
            ];
        }

        $tokensA = self::tokenizeWords($original);
        $tokensB = self::tokenizeWords($proposed);

        $ops = self::computeLcsDiff($tokensA, $tokensB);
        $chunks = self::mergeContiguousChunks($ops);

        $additions = 0;
        $deletions = 0;
        $unchanged = 0;

        foreach ($chunks as $chunk) {
            $len = mb_strlen($chunk['value']);
            if ($chunk['type'] === 'added') {
                $additions += $len;
            } elseif ($chunk['type'] === 'removed') {
                $deletions += $len;
            } else {
                $unchanged += $len;
            }
        }

        return [
            'original' => $original,
            'proposed' => $proposed,
            'has_changes' => true,
            'chunks' => $chunks,
            'stats' => [
                'additions' => $additions,
                'deletions' => $deletions,
                'unchanged' => $unchanged,
            ],
        ];
    }

    /**
     * Tokenize text into words and whitespace runs.
     *
     * @return array<int, string>
     */
    protected static function tokenizeWords(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/(\s+|[^\p{L}\p{N}\s]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $tokens !== false ? array_values($tokens) : [$text];
    }

    /**
     * Compute LCS diff over two token lists.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{type: 'common'|'added'|'removed', value: string}>
     */
    protected static function computeLcsDiff(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n === 0 && $m === 0) {
            return [];
        }

        if ($n === 0) {
            return array_map(fn ($token) => ['type' => 'added', 'value' => $token], $b);
        }

        if ($m === 0) {
            return array_map(fn ($token) => ['type' => 'removed', 'value' => $token], $a);
        }

        // Build LCS length matrix
        $dp = [];
        for ($i = 0; $i <= $n; $i++) {
            $dp[$i] = array_fill(0, $m + 1, 0);
        }

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                if ($a[$i] === $b[$j]) {
                    $dp[$i + 1][$j + 1] = $dp[$i][$j] + 1;
                } else {
                    $dp[$i + 1][$j + 1] = max($dp[$i][$j + 1], $dp[$i + 1][$j]);
                }
            }
        }

        // Backtrack to find differences
        $i = $n;
        $j = $m;
        $result = [];

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                $result[] = ['type' => 'common', 'value' => $a[$i - 1]];
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $dp[$i][$j - 1] >= $dp[$i - 1][$j])) {
                $result[] = ['type' => 'added', 'value' => $b[$j - 1]];
                $j--;
            } elseif ($i > 0) {
                $result[] = ['type' => 'removed', 'value' => $a[$i - 1]];
                $i--;
            }
        }

        return array_reverse($result);
    }

    /**
     * Merge consecutive tokens of the same diff type.
     *
     * @param array<int, array{type: 'common'|'added'|'removed', value: string}> $ops
     * @return array<int, array{type: 'common'|'added'|'removed', value: string}>
     */
    protected static function mergeContiguousChunks(array $ops): array
    {
        if (empty($ops)) {
            return [];
        }

        $merged = [];
        $current = $ops[0];

        for ($k = 1; $k < count($ops); $k++) {
            if ($ops[$k]['type'] === $current['type']) {
                $current['value'] .= $ops[$k]['value'];
            } else {
                $merged[] = $current;
                $current = $ops[$k];
            }
        }

        $merged[] = $current;

        return $merged;
    }
}
