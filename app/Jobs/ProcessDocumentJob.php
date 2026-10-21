<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\DocumentPage;
use App\Services\PdfExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Document $document)
    {
    }

    public function handle(PdfExtractor $extractor): void
    {
        $this->document->refresh();

        // If document was already deleted while in queue, exit gracefully
        if (! $this->document->exists) {
            return;
        }

        $this->document->update([
            'status' => Document::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        $disk = Storage::disk('local');
        if (! $this->document->file_path || ! $disk->exists($this->document->file_path)) {
            $this->document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => 'File dokumen tidak ditemukan di penyimpanan server.',
            ]);

            return;
        }

        $absolutePath = $disk->path($this->document->file_path);

        try {
            $result = $extractor->extract($absolutePath);

            if (! $result['success']) {
                $this->document->update([
                    'status' => Document::STATUS_FAILED,
                    'total_pages' => $result['total_pages'],
                    'error_message' => $result['error'] ?? 'Gagal mengekstrak teks dari dokumen PDF.',
                ]);

                return;
            }

            DB::transaction(function () use ($result) {
                // Clear existing pages if retrying
                $this->document->pages()->delete();

                $now = now();
                $pagesToInsert = [];

                foreach ($result['pages'] as $pageData) {
                    $pagesToInsert[] = [
                        'project_id' => $this->document->project_id,
                        'document_id' => $this->document->id,
                        'page_number' => $pageData['page_number'],
                        'content' => $pageData['text'],
                        'character_count' => mb_strlen($pageData['text']),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($pagesToInsert)) {
                    DocumentPage::insert($pagesToInsert);
                }

                $status = $result['is_partial']
                    ? Document::STATUS_PARTIAL
                    : Document::STATUS_PROCESSED;

                $errorMessage = $result['is_partial']
                    ? 'Sebagian halaman tidak memiliki teks terbaca atau memerlukan OCR lebih lanjut.'
                    : null;

                $this->document->update([
                    'status' => $status,
                    'total_pages' => $result['total_pages'],
                    'error_message' => $errorMessage,
                ]);
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
                'error_message' => $exception?->getMessage() ?? 'Ekstraksi dokumen gagal diproses oleh queue worker.',
            ]);
        }
    }
}
