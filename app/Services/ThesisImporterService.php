<?php

namespace App\Services;

use App\Models\Project;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ZipArchive;

class ThesisImporterService
{
    public function __construct(
        protected PdfExtractor $pdfExtractor
    ) {
    }

    /**
     * Parse an uploaded thesis draft (DOCX or text-based PDF) into candidate chapters.
     *
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     preview_text: string,
     *     content: array<string, mixed>,
     *     selected: bool
     * }>
     */
    public function parseFile(UploadedFile|string $file, ?string $originalExtension = null): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower(
            $originalExtension
            ?? ($file instanceof UploadedFile ? $file->getClientOriginalExtension() : pathinfo($filePath, PATHINFO_EXTENSION))
        );

        if ($extension === 'docx') {
            return $this->parseDocx($filePath);
        }

        if ($extension === 'pdf') {
            return $this->parsePdf($filePath);
        }

        throw new InvalidArgumentException('Format file tidak didukung. Harap unggah file DOCX atau PDF.');
    }

    /**
     * Import confirmed candidate chapters into the project outline as Chapter Documents.
     *
     * @param array<int, array{title: string, content?: array<string, mixed>|null}> $chaptersData
     * @return Collection<int, \App\Models\Chapter>
     */
    public function import(Project $project, array $chaptersData): Collection
    {
        return DB::transaction(function () use ($project, $chaptersData) {
            $maxOrder = (int) $project->chapters()
                ->whereNull('parent_id')
                ->max('order');

            $created = collect();

            foreach ($chaptersData as $data) {
                $title = trim($data['title'] ?? '');
                if ($title === '') {
                    continue;
                }

                $content = $data['content'] ?? null;
                if (empty($content)) {
                    $content = $this->createDefaultTiptapDoc($title, []);
                }

                $maxOrder++;
                $chapter = $project->outlineItems()->create([
                    'parent_id' => null,
                    'title' => $title,
                    'content' => $content,
                    'order' => $maxOrder,
                ]);

                $created->push($chapter);
            }

            return $created;
        });
    }

    /**
     * Parse DOCX file into candidate chapters by scanning paragraph styles and heading patterns.
     *
     * @return array<int, array{id: string, title: string, preview_text: string, content: array<string, mixed>, selected: bool}>
     */
    public function parseDocx(string $filePath): array
    {
        $paragraphs = $this->extractDocxParagraphs($filePath);

        return $this->splitParagraphsIntoCandidateChapters($paragraphs);
    }

    /**
     * Extract paragraphs and associated style information from a DOCX file.
     *
     * @return array<int, array{style: string, text: string, level: ?int}>
     */
    protected function extractDocxParagraphs(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new InvalidArgumentException('Gagal membuka file DOCX. File mungkin rusak.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false || empty($xml)) {
            throw new InvalidArgumentException('Dokumen DOCX tidak memiliki konten teks yang valid.');
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $results = [];
        $pNodes = $xpath->query('//w:p');

        if ($pNodes) {
            foreach ($pNodes as $p) {
                $styleNode = $xpath->query('.//w:pPr/w:pStyle/@w:val', $p)->item(0);
                $style = $styleNode ? (string) $styleNode->nodeValue : '';

                $text = '';
                $textNodes = $xpath->query('.//w:t', $p);
                if ($textNodes) {
                    foreach ($textNodes as $t) {
                        $text .= $t->nodeValue;
                    }
                }

                $trimmedText = trim($text);
                $headingLevel = $this->detectHeadingLevelFromStyle($style);

                $results[] = [
                    'style' => $style,
                    'text' => $trimmedText,
                    'level' => $headingLevel,
                ];
            }
        }

        return $results;
    }

    /**
     * Detect heading level (1, 2, 3) from style string.
     */
    protected function detectHeadingLevelFromStyle(string $style): ?int
    {
        $normalized = strtolower(str_replace([' ', '_', '-'], '', $style));

        if (in_array($normalized, ['heading1', 'title', 'judul1', 'bab', 'chapter'])) {
            return 1;
        }
        if (in_array($normalized, ['heading2', 'subtitle', 'judul2', 'subbab'])) {
            return 2;
        }
        if (in_array($normalized, ['heading3', 'judul3', 'subsubbab'])) {
            return 3;
        }

        return null;
    }

    /**
     * Parse text-based PDF file into candidate chapters using PdfExtractor.
     *
     * @return array<int, array{id: string, title: string, preview_text: string, content: array<string, mixed>, selected: bool}>
     */
    public function parsePdf(string $filePath): array
    {
        $extraction = $this->pdfExtractor->extract($filePath);

        if (! ($extraction['success'] ?? false) || empty($extraction['pages'])) {
            throw new InvalidArgumentException('Tidak ada teks yang dapat diekstrak dari dokumen PDF.');
        }

        $paragraphs = [];
        foreach ($extraction['pages'] as $page) {
            $rawText = $page['text'] ?? '';
            // Split page text by double newlines or lines
            $lines = preg_split("/\r\n|\n|\r/", $rawText);
            $buffer = '';

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    if ($buffer !== '') {
                        $paragraphs[] = [
                            'style' => '',
                            'text' => $buffer,
                            'level' => null,
                        ];
                        $buffer = '';
                    }
                    continue;
                }

                // If line looks like a chapter header, flush buffer first and push header
                if ($this->isChapterHeadingLine($trimmed)) {
                    if ($buffer !== '') {
                        $paragraphs[] = [
                            'style' => '',
                            'text' => $buffer,
                            'level' => null,
                        ];
                        $buffer = '';
                    }
                    $paragraphs[] = [
                        'style' => 'Heading1',
                        'text' => $trimmed,
                        'level' => 1,
                    ];
                } else {
                    $buffer = $buffer === '' ? $trimmed : $buffer.' '.$trimmed;
                }
            }

            if ($buffer !== '') {
                $paragraphs[] = [
                    'style' => '',
                    'text' => $buffer,
                    'level' => null,
                ];
            }
        }

        return $this->splitParagraphsIntoCandidateChapters($paragraphs);
    }

    /**
     * Check if a line matches chapter heading markers (BAB [I-V] or common thesis section markers).
     */
    public function isChapterHeadingLine(string $text): bool
    {
        // 1. Chapter marker: BAB [I-V] or BAB [0-9]+
        if (preg_match('/^\s*BAB\s+([IVXLCDM]+|\d+)\b/i', $text)) {
            return true;
        }

        // 2. Standalone front/back matter uppercase headings
        if (preg_match('/^\s*(ABSTRAK|ABSTRACT|KATA\s+PENGANTAR|DAFTAR\s+ISI|DAFTAR\s+TABEL|DAFTAR\s+GAMBAR|DAFTAR\s+PUSTAKA|LAMPIRAN)\b/i', $text)) {
            return mb_strlen($text) <= 80;
        }

        return false;
    }

    /**
     * Split a sequence of extracted paragraphs into candidate chapter records.
     *
     * @param array<int, array{style: string, text: string, level: ?int}> $paragraphs
     * @return array<int, array{id: string, title: string, preview_text: string, content: array<string, mixed>, selected: bool}>
     */
    public function splitParagraphsIntoCandidateChapters(array $paragraphs): array
    {
        $rawChapters = [];
        $currentChapter = null;
        $totalParagraphs = count($paragraphs);

        for ($i = 0; $i < $totalParagraphs; $i++) {
            $p = $paragraphs[$i];
            $text = $p['text'];
            $level = $p['level'];

            if ($text === '') {
                continue;
            }

            $isBabPattern = (bool) preg_match('/^\s*BAB\s+([IVXLCDM]+|\d+)\b/i', $text);
            $isHeadingOne = ($level === 1);
            $isFrontMatter = $this->isChapterHeadingLine($text);

            $isHeader = $isBabPattern || $isHeadingOne || $isFrontMatter;

            if ($isHeader) {
                // If chapter marker is just "BAB I" without title, check if next paragraph is the title
                $title = $text;
                if (preg_match('/^\s*BAB\s+([IVXLCDM]+|\d+)\s*[:\.\-]?\s*$/i', $text)) {
                    if (isset($paragraphs[$i + 1])) {
                        $nextText = $paragraphs[$i + 1]['text'];
                        $nextIsBab = (bool) preg_match('/^\s*BAB\s+([IVXLCDM]+|\d+)\b/i', $nextText);
                        if (! $nextIsBab && mb_strlen($nextText) > 0 && mb_strlen($nextText) <= 120) {
                            $title = $text.' '.$nextText;
                            $i++; // advance past combined title paragraph
                        }
                    }
                }

                if ($currentChapter !== null) {
                    $rawChapters[] = $currentChapter;
                }

                $currentChapter = [
                    'title' => $title,
                    'body_paragraphs' => [],
                ];
            } else {
                if ($currentChapter === null) {
                    // Pre-heading content (front matter or draft without headers)
                    $currentChapter = [
                        'title' => 'Bagian Awal',
                        'body_paragraphs' => [],
                    ];
                }

                $currentChapter['body_paragraphs'][] = $p;
            }
        }

        if ($currentChapter !== null) {
            $rawChapters[] = $currentChapter;
        }

        // If no chapters found, fallback to single whole draft candidate
        if (empty($rawChapters)) {
            $rawChapters[] = [
                'title' => 'Draf Skripsi',
                'body_paragraphs' => array_filter($paragraphs, fn ($p) => $p['text'] !== ''),
            ];
        }

        // Format candidate chapters with preview text and Tiptap document content
        $candidates = [];
        $candidateIndex = 1;

        foreach ($rawChapters as $raw) {
            $chapterTitle = $raw['title'];
            $bodyParagraphs = $raw['body_paragraphs'];

            $plainTextChunks = [];
            foreach ($bodyParagraphs as $bp) {
                if ($bp['text'] !== '') {
                    $plainTextChunks[] = $bp['text'];
                }
            }

            $combinedBodyText = implode("\n\n", $plainTextChunks);
            $previewSnippet = mb_substr($combinedBodyText, 0, 280);
            if (mb_strlen($combinedBodyText) > 280) {
                $previewSnippet .= '...';
            }

            $tiptapDoc = $this->createDefaultTiptapDoc($chapterTitle, $bodyParagraphs);

            $candidates[] = [
                'id' => 'candidate-'.$candidateIndex,
                'title' => $chapterTitle,
                'preview_text' => $previewSnippet ?: '(Tidak ada paragraf teks)',
                'content' => $tiptapDoc,
                'selected' => true,
            ];

            $candidateIndex++;
        }

        return $candidates;
    }

    /**
     * Build Tiptap JSON document structure from chapter title and body paragraphs.
     *
     * @param array<int, array{style: string, text: string, level: ?int}> $bodyParagraphs
     * @return array<string, mixed>
     */
    protected function createDefaultTiptapDoc(string $title, array $bodyParagraphs): array
    {
        $nodes = [];

        // Root Chapter Title as Level 1 Heading
        $nodes[] = [
            'type' => 'heading',
            'attrs' => ['level' => 1],
            'content' => [
                ['type' => 'text', 'text' => $title],
            ],
        ];

        if (empty($bodyParagraphs)) {
            $nodes[] = [
                'type' => 'paragraph',
                'content' => [],
            ];
        } else {
            foreach ($bodyParagraphs as $bp) {
                $text = $bp['text'];
                $level = $bp['level'];

                if ($text === '') {
                    continue;
                }

                if ($level !== null && in_array($level, [2, 3])) {
                    $nodes[] = [
                        'type' => 'heading',
                        'attrs' => ['level' => $level],
                        'content' => [
                            ['type' => 'text', 'text' => $text],
                        ],
                    ];
                } else {
                    $nodes[] = [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => $text],
                        ],
                    ];
                }
            }
        }

        return [
            'type' => 'doc',
            'content' => $nodes,
        ];
    }
}
