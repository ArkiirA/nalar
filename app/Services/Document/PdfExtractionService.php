<?php

namespace App\Services\Document;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfExtractionService
{
    public function __construct(
        protected ?Parser $parser = null
    ) {
        $this->parser = $parser ?: new Parser();
    }

    /**
     * Extract text and metadata page-by-page from an uploaded PDF document.
     */
    public function extract(Document $document): Document
    {
        $document->update([
            'status' => Document::STATUS_PROCESSING,
            'error_message' => null,
        ]);

        $disk = Storage::disk('local');
        $fullPath = $disk->path($document->file_path);

        if (!file_exists($fullPath)) {
            $document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => "Berkas PDF tidak ditemukan pada path: {$document->file_path}",
            ]);

            return $document;
        }

        try {
            $pdf = $this->parser->parseFile($fullPath);
            $pages = $pdf->getPages();
            $pageCount = count($pages);

            $pagesData = [];
            $allTextParts = [];
            $emptyPageCount = 0;

            foreach ($pages as $index => $page) {
                $pageNumber = $index + 1;
                $text = trim($page->getText());

                if (empty($text)) {
                    $emptyPageCount++;
                }

                $wordCount = !empty($text) ? count(preg_split('/\s+/', $text)) : 0;

                $pagesData[] = [
                    'page' => $pageNumber,
                    'text' => $text,
                    'word_count' => $wordCount,
                ];

                if (!empty($text)) {
                    $allTextParts[] = "[Halaman {$pageNumber}]\n" . $text;
                }
            }

            // Extract document metadata details
            $details = $pdf->getDetails();
            $extractedMetadata = [
                'author' => $details['Author'] ?? null,
                'creator' => $details['Creator'] ?? null,
                'producer' => $details['Producer'] ?? null,
                'creation_date' => $details['CreationDate'] ?? null,
                'title' => $details['Title'] ?? null,
                'keywords' => $details['Keywords'] ?? null,
            ];

            // Determine status
            $combinedText = implode("\n\n", $allTextParts);
            $status = Document::STATUS_PROCESSED;

            if (empty($combinedText)) {
                $status = Document::STATUS_PARTIAL;
                $errorMessage = 'Tidak ditemukan teks digital pada PDF (kemungkinan PDF hasil pindaian/scan murni).';
            } elseif ($emptyPageCount > 0 && $emptyPageCount < $pageCount) {
                $status = Document::STATUS_PARTIAL;
                $errorMessage = "Teks berhasil diekstrak sebagian ({$emptyPageCount} dari {$pageCount} halaman kosong).";
            } else {
                $errorMessage = null;
            }

            $document->update([
                'status' => $status,
                'page_count' => $pageCount,
                'pages_data' => $pagesData,
                'extracted_text' => $combinedText,
                'metadata' => array_filter($extractedMetadata),
                'error_message' => $errorMessage,
            ]);

            return $document;
        } catch (Throwable $e) {
            $document->update([
                'status' => Document::STATUS_FAILED,
                'error_message' => 'Gagal mengekstrak teks PDF: ' . $e->getMessage(),
            ]);

            return $document;
        }
    }
}
