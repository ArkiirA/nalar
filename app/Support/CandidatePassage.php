<?php

namespace App\Support;

use App\Models\DocumentChunk;

class CandidatePassage
{
    public function __construct(
        public int $id,
        public int $document_id,
        public string $document_title,
        public int $page_number,
        public string $content,
        public float $score,
    ) {
    }

    /**
     * Create a CandidatePassage from a retrieved DocumentChunk model.
     */
    public static function fromChunk(DocumentChunk $chunk, float $score): self
    {
        return new self(
            id: (int) $chunk->id,
            document_id: (int) $chunk->document_id,
            document_title: (string) ($chunk->document?->title ?? 'Untitled Document'),
            page_number: (int) $chunk->page_number,
            content: (string) $chunk->content,
            score: $score,
        );
    }

    /**
     * Convert CandidatePassage to array.
     *
     * @return array{id: int, document_id: int, document_title: string, page_number: int, content: string, score: float}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'document_title' => $this->document_title,
            'page_number' => $this->page_number,
            'content' => $this->content,
            'score' => $this->score,
        ];
    }
}
