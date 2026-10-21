<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\DocumentChunker;
use App\Services\GeminiEmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateDocumentEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document)
    {
    }

    public function handle(DocumentChunker $chunker, GeminiEmbeddingService $embeddingService): void
    {
        $this->document->refresh();

        if (! $this->document->exists) {
            return;
        }

        $pages = $this->document->pages()->orderBy('page_number')->get();

        if ($pages->isEmpty()) {
            return;
        }

        $allChunks = [];
        $globalIndex = 0;

        foreach ($pages as $page) {
            $content = trim($page->content ?? '');
            if ($content === '') {
                continue;
            }

            $pageChunks = $chunker->chunkText(
                $content,
                $page->page_number,
                DocumentChunker::DEFAULT_TARGET_TOKENS,
                $globalIndex
            );

            foreach ($pageChunks as $chunkData) {
                $allChunks[] = [
                    'project_id' => $this->document->project_id,
                    'document_id' => $this->document->id,
                    'document_page_id' => $page->id,
                    'page_number' => $chunkData['page_number'],
                    'chunk_index' => $chunkData['chunk_index'],
                    'content' => $chunkData['content'],
                    'token_count' => $chunkData['token_count'],
                ];
                $globalIndex++;
            }
        }

        if (empty($allChunks)) {
            $this->document->chunks()->delete();

            return;
        }

        try {
            // Generate vector embeddings via Gemini Embedding Service
            $texts = array_column($allChunks, 'content');
            $embeddings = $embeddingService->generateBatchEmbeddings($texts);

            $now = now();
            $recordsToInsert = [];
            foreach ($allChunks as $i => $chunk) {
                $chunkEmbedding = $embeddings[$i] ?? null;
                $recordsToInsert[] = [
                    'project_id' => $chunk['project_id'],
                    'document_id' => $chunk['document_id'],
                    'document_page_id' => $chunk['document_page_id'],
                    'page_number' => $chunk['page_number'],
                    'chunk_index' => $chunk['chunk_index'],
                    'content' => $chunk['content'],
                    'token_count' => $chunk['token_count'],
                    'embedding' => $chunkEmbedding !== null
                        ? json_encode(array_values(array_map('floatval', $chunkEmbedding)))
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use ($recordsToInsert) {
                $this->document->chunks()->delete();
                DocumentChunk::insert($recordsToInsert);
            });
        } catch (Throwable $e) {
            $this->document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->document->exists) {
            $this->document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => $exception?->getMessage() ?? 'Gagal membuat vektor embedding dokumen.',
            ]);
        }
    }
}
