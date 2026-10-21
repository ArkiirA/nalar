<?php

namespace App\Services;

class DocumentChunker
{
    public const DEFAULT_TARGET_TOKENS = 500;

    /**
     * Estimate token count for English and Indonesian text.
     * Uses hybrid character length and word count approximation.
     */
    public function estimateTokenCount(string $text): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = is_array($words) ? count($words) : 0;
        $charCount = mb_strlen($trimmed);

        $estimated = (int) ceil(max($charCount / 4, $wordCount * 1.3));

        return max(1, $estimated);
    }

    /**
     * Chunk text strictly bounded to a single physical page.
     *
     * @return array<int, array{page_number: int, chunk_index: int, content: string, token_count: int}>
     */
    public function chunkText(
        string $text,
        int $pageNumber,
        int $targetTokens = self::DEFAULT_TARGET_TOKENS,
        int $startIndex = 0
    ): array {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return [];
        }

        $totalTokens = $this->estimateTokenCount($trimmed);
        $maxTokens = (int) ceil($targetTokens * 1.1);

        // Fits in a single chunk
        if ($totalTokens <= $maxTokens) {
            return [[
                'page_number' => $pageNumber,
                'chunk_index' => $startIndex,
                'content' => $trimmed,
                'token_count' => $totalTokens,
            ]];
        }

        // Split by paragraphs (2 or more newlines)
        $rawParagraphs = preg_split('/(?:\r?\n){2,}/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        $paragraphs = is_array($rawParagraphs) ? array_map('trim', $rawParagraphs) : [$trimmed];

        $chunks = [];
        $currentSegments = [];
        $currentTokens = 0;
        $currentIndex = $startIndex;

        foreach ($paragraphs as $para) {
            if ($para === '') {
                continue;
            }

            $paraTokens = $this->estimateTokenCount($para);

            // Single paragraph exceeds target tokens: split into sentences
            if ($paraTokens > $maxTokens) {
                // Flush accumulated segments first
                if (! empty($currentSegments)) {
                    $content = implode("\n\n", $currentSegments);
                    $chunks[] = [
                        'page_number' => $pageNumber,
                        'chunk_index' => $currentIndex++,
                        'content' => $content,
                        'token_count' => $this->estimateTokenCount($content),
                    ];
                    $currentSegments = [];
                    $currentTokens = 0;
                }

                $sentenceChunks = $this->splitParagraphBySentences($para, $targetTokens);
                foreach ($sentenceChunks as $sentenceText) {
                    $sTokens = $this->estimateTokenCount($sentenceText);
                    $chunks[] = [
                        'page_number' => $pageNumber,
                        'chunk_index' => $currentIndex++,
                        'content' => $sentenceText,
                        'token_count' => $sTokens,
                    ];
                }
                continue;
            }

            // If adding paragraph exceeds target tokens, flush current buffer
            if (! empty($currentSegments) && ($currentTokens + $paraTokens > $maxTokens)) {
                $content = implode("\n\n", $currentSegments);
                $chunks[] = [
                    'page_number' => $pageNumber,
                    'chunk_index' => $currentIndex++,
                    'content' => $content,
                    'token_count' => $this->estimateTokenCount($content),
                ];
                $currentSegments = [];
                $currentTokens = 0;
            }

            $currentSegments[] = $para;
            $currentTokens += $paraTokens;
        }

        if (! empty($currentSegments)) {
            $content = implode("\n\n", $currentSegments);
            $chunks[] = [
                'page_number' => $pageNumber,
                'chunk_index' => $currentIndex++,
                'content' => $content,
                'token_count' => $this->estimateTokenCount($content),
            ];
        }

        return $chunks;
    }

    /**
     * Chunk multiple pages, strictly maintaining physical page numbers.
     *
     * @param iterable<array{page_number: int, text: string}> $pages
     * @return array<int, array{page_number: int, chunk_index: int, content: string, token_count: int}>
     */
    public function chunkPages(iterable $pages, int $targetTokens = self::DEFAULT_TARGET_TOKENS): array
    {
        $allChunks = [];
        $globalIndex = 0;

        foreach ($pages as $page) {
            $pageNumber = is_array($page) ? $page['page_number'] : $page->page_number;
            $text = is_array($page) ? ($page['text'] ?? $page['content'] ?? '') : ($page->content ?? '');

            $pageChunks = $this->chunkText($text, $pageNumber, $targetTokens, $globalIndex);
            foreach ($pageChunks as $chunk) {
                $allChunks[] = $chunk;
                $globalIndex++;
            }
        }

        return $allChunks;
    }

    /**
     * Split an oversized paragraph into sentence-bounded chunks.
     *
     * @return array<int, string>
     */
    protected function splitParagraphBySentences(string $paragraph, int $targetTokens): array
    {
        $rawSentences = preg_split('/(?<=[.!?])\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = is_array($rawSentences) ? array_map('trim', $rawSentences) : [$paragraph];

        $slices = [];
        $current = [];
        $currentTokens = 0;
        $maxTokens = (int) ceil($targetTokens * 1.1);

        foreach ($sentences as $sentence) {
            if ($sentence === '') {
                continue;
            }

            $sTokens = $this->estimateTokenCount($sentence);

            // Single sentence exceeds max tokens: split by word chunks
            if ($sTokens > $maxTokens) {
                if (! empty($current)) {
                    $slices[] = implode(' ', $current);
                    $current = [];
                    $currentTokens = 0;
                }

                $wordSlices = $this->splitSentenceByWords($sentence, $targetTokens);
                foreach ($wordSlices as $ws) {
                    $slices[] = $ws;
                }
                continue;
            }

            if (! empty($current) && ($currentTokens + $sTokens > $maxTokens)) {
                $slices[] = implode(' ', $current);
                $current = [];
                $currentTokens = 0;
            }

            $current[] = $sentence;
            $currentTokens += $sTokens;
        }

        if (! empty($current)) {
            $slices[] = implode(' ', $current);
        }

        return $slices;
    }

    /**
     * Split an oversized sentence into word-bounded slices.
     *
     * @return array<int, string>
     */
    protected function splitSentenceByWords(string $sentence, int $targetTokens): array
    {
        $words = preg_split('/\s+/u', $sentence, -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($words) || empty($words)) {
            return [$sentence];
        }

        $slices = [];
        $current = [];
        $currentTokens = 0;

        foreach ($words as $word) {
            $wTokens = $this->estimateTokenCount($word);
            if (! empty($current) && ($currentTokens + $wTokens > $targetTokens)) {
                $slices[] = implode(' ', $current);
                $current = [];
                $currentTokens = 0;
            }

            $current[] = $word;
            $currentTokens += $wTokens;
        }

        if (! empty($current)) {
            $slices[] = implode(' ', $current);
        }

        return $slices;
    }
}
