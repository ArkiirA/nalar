<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class PdfExtractor
{
    /**
     * @var (callable(string, int): ?string)|null
     */
    protected $ocrRunner = null;

    public function __construct(
        protected string $pdftotextBinary = 'pdftotext',
        protected string $pdfinfoBinary = 'pdfinfo',
        protected string $pdftoppmBinary = 'pdftoppm',
        protected string $tesseractBinary = 'tesseract',
    ) {
    }

    /**
     * Set a custom OCR runner callback for tests or alternative engines.
     * Signature: function(string $pdfPath, int $pageNumber): ?string
     */
    public function setOcrRunner(?callable $runner): self
    {
        $this->ocrRunner = $runner;

        return $this;
    }

    /**
     * Extract text page-by-page from a PDF file preserving page boundaries.
     *
     * @return array{
     *     success: bool,
     *     is_partial: bool,
     *     total_pages: int,
     *     pages: array<int, array{page_number: int, text: string, method: string}>,
     *     error: ?string
     * }
     */
    public function extract(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            return [
                'success' => false,
                'is_partial' => false,
                'total_pages' => 0,
                'pages' => [],
                'error' => 'File tidak ditemukan di penyimpanan.',
            ];
        }

        $totalPages = $this->determineTotalPages($pdfPath);

        if ($totalPages === null || $totalPages <= 0) {
            return [
                'success' => false,
                'is_partial' => false,
                'total_pages' => 0,
                'pages' => [],
                'error' => 'File PDF tidak valid atau gagal dibaca.',
            ];
        }

        $pages = [];
        $nonEmptyCount = 0;

        for ($p = 1; $p <= $totalPages; $p++) {
            $pageResult = $this->extractPageText($pdfPath, $p);
            $text = trim($pageResult['text']);
            $method = $pageResult['method'];

            // Fallback to OCR if pdftotext extracted empty or whitespace-only text
            if (empty($text) && $this->canRunOcr()) {
                $ocrText = $this->runOcr($pdfPath, $p);
                if (! empty($ocrText)) {
                    $text = trim($ocrText);
                    $method = 'tesseract-ocr';
                }
            }

            if (! empty($text)) {
                $nonEmptyCount++;
            }

            $pages[] = [
                'page_number' => $p,
                'text' => $text,
                'method' => $method,
            ];
        }

        $success = $nonEmptyCount > 0;
        $isPartial = $nonEmptyCount > 0 && $nonEmptyCount < $totalPages;

        return [
            'success' => $success,
            'is_partial' => $isPartial,
            'total_pages' => $totalPages,
            'pages' => $pages,
            'error' => $success ? null : 'Tidak ada teks yang dapat diekstrak dari dokumen PDF.',
        ];
    }

    /**
     * Determine total page count via pdfinfo or pdftotext layout dump.
     */
    protected function determineTotalPages(string $pdfPath): ?int
    {
        $infoProcess = Process::run([$this->pdfinfoBinary, $pdfPath]);

        if ($infoProcess->successful()) {
            if (preg_match('/^Pages:\s*(\d+)/m', $infoProcess->output(), $matches)) {
                return (int) $matches[1];
            }
        }

        // Fallback: run pdftotext -layout to count form feed page breaks
        $textProcess = Process::run([$this->pdftotextBinary, '-layout', $pdfPath, '-']);
        if ($textProcess->successful()) {
            $output = $textProcess->output();
            if (str_contains($output, "\x0c")) {
                $parts = explode("\x0c", $output);
                // Last item after trailing form feed is usually empty
                if (empty(end($parts))) {
                    array_pop($parts);
                }

                return max(1, count($parts));
            }

            return 1;
        }

        return null;
    }

    /**
     * Extract text for a single page using pdftotext with layout preservation.
     *
     * @return array{text: string, method: string}
     */
    protected function extractPageText(string $pdfPath, int $page): array
    {
        $process = Process::run([
            $this->pdftotextBinary,
            '-layout',
            '-f', (string) $page,
            '-l', (string) $page,
            $pdfPath,
            '-',
        ]);

        if (! $process->successful()) {
            return ['text' => '', 'method' => 'pdftotext'];
        }

        $text = str_replace("\x0c", '', $process->output());

        return ['text' => $text, 'method' => 'pdftotext'];
    }

    /**
     * Check if OCR capability is available.
     */
    protected function canRunOcr(): bool
    {
        if ($this->ocrRunner !== null) {
            return true;
        }

        $check = Process::run(['which', $this->tesseractBinary]);

        return $check->successful();
    }

    /**
     * Run OCR on a specific page of the PDF.
     */
    protected function runOcr(string $pdfPath, int $page): ?string
    {
        if ($this->ocrRunner !== null) {
            return ($this->ocrRunner)($pdfPath, $page);
        }

        $tempPrefix = sys_get_temp_dir().'/nalar_ocr_'.uniqid().'_p'.$page;

        try {
            // Render the specific page to PNG at 150 DPI
            $ppmProcess = Process::run([
                $this->pdftoppmBinary,
                '-png',
                '-r', '150',
                '-f', (string) $page,
                '-l', (string) $page,
                $pdfPath,
                $tempPrefix,
            ]);

            if (! $ppmProcess->successful()) {
                return null;
            }

            $images = glob($tempPrefix.'*.png') ?: [];
            if (empty($images)) {
                return null;
            }

            $imagePath = $images[0];

            // Run tesseract on the rendered page image
            $ocrProcess = Process::run([
                $this->tesseractBinary,
                $imagePath,
                'stdout',
            ]);

            return $ocrProcess->successful() ? $ocrProcess->output() : null;
        } finally {
            $images = glob($tempPrefix.'*') ?: [];
            foreach ($images as $img) {
                @unlink($img);
            }
        }
    }
}
