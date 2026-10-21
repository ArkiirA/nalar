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
}
