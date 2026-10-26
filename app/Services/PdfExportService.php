<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use App\Models\Reference;
use App\Support\CslFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfExportService
{
    /**
     * @var (callable(string, string): bool)|null
     */
    protected $runner = null;

    /**
     * Set a custom runner callback for deterministic testing.
     * Signature: function(string $htmlPath, string $pdfPath): bool
     */
    public function setRunner(?callable $runner): self
    {
        $this->runner = $runner;

        return $this;
    }

    /**
     * Compile project thesis outline into a campus-styled PDF file.
     *
     * @return string Absolute path to the generated .pdf file.
     *
     * @throws RuntimeException
     */
    public function generate(Project $project, ?string $outputPath = null): string
    {
        $html = $this->renderHtml($project);

        $tempDir = config('filesystems.disks.local.root', storage_path('app'));
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }

        $id = uniqid('thesis_export_');
        $htmlPath = rtrim($tempDir, '/')."/{$id}.html";
        $pdfPath = $outputPath ?: rtrim($tempDir, '/')."/{$id}.pdf";

        file_put_contents($htmlPath, $html);

        try {
            if ($this->runner !== null) {
                ($this->runner)($htmlPath, $pdfPath);
            } else {
                $binary = $this->resolveChromiumBinary();

                $result = Process::timeout(120)->run([
                    $binary,
                    '--headless=new',
                    '--disable-gpu',
                    '--no-sandbox',
                    '--print-to-pdf='.$pdfPath,
                    $htmlPath,
                ]);

                if (! $result->successful() || ! file_exists($pdfPath) || filesize($pdfPath) === 0) {
                    $error = $result->errorOutput() ?: $result->output();
                    throw new RuntimeException("Headless Chromium gagal mengompilasi PDF: {$error}");
                }
            }
        } finally {
            if (file_exists($htmlPath)) {
                @unlink($htmlPath);
            }
        }

        if (! file_exists($pdfPath) || filesize($pdfPath) === 0) {
            throw new RuntimeException('Berkas PDF tidak berhasil dibuat.');
        }

        return $pdfPath;
    }

    /**
     * Render the entire thesis document to a fully styled HTML view.
     */
    public function renderHtml(Project $project): string
    {
        $formatting = $this->resolveFormatting($project);

        // Pre-cache project references strictly scoped to tenant boundary
        $projectReferences = $project->references()->get()->keyBy('id');

        $allChapters = $project->chapters()->orderBy('order')->get()->groupBy('parent_id');
        $rootChapters = $allChapters->get(null) ?? collect();

        $toc = [];
        $tables = [];
        $figures = [];
        $renderedChapters = [];
        $citedReferenceIds = [];

        foreach ($rootChapters as $rootChapter) {
            $this->compileChapterTree(
                $rootChapter,
                1,
                $allChapters,
                $projectReferences,
                $toc,
                $tables,
                $figures,
                $renderedChapters,
                $citedReferenceIds
            );
        }

        $bibliography = $this->compileBibliography($project, $projectReferences, array_unique($citedReferenceIds));

        // Calculate continuous sequential page numbers for TOC and front matter
        $currentPage = 1; // DAFTAR ISI on page 1
        if (count($tables) > 0) {
            $currentPage++;
            foreach ($tables as &$tbl) {
                $tbl['page'] = $currentPage;
            }
            unset($tbl);
        }
        if (count($figures) > 0) {
            $currentPage++;
            foreach ($figures as &$fig) {
                $fig['page'] = $currentPage;
            }
            unset($fig);
        }

        foreach ($renderedChapters as $rChap) {
            if ($rChap['level'] === 1) {
                $currentPage++;
            }
            foreach ($toc as &$tocItem) {
                if ($tocItem['id'] === $rChap['id']) {
                    $tocItem['page'] = $currentPage;
                }
            }
            unset($tocItem);
        }

        $bibliographyPage = null;
        if (count($bibliography) > 0) {
            $currentPage++;
            $bibliographyPage = $currentPage;
        }

        $margins = [
            'top' => $formatting['margins']['top'] ?? '4cm',
            'left' => $formatting['margins']['left'] ?? '4cm',
            'bottom' => $formatting['margins']['bottom'] ?? '3cm',
            'right' => $formatting['margins']['right'] ?? '3cm',
        ];

        return view('exports.thesis-pdf', [
            'project' => $project,
            'formatting' => $formatting,
            'margins' => $margins,
            'toc' => $toc,
            'tables' => $tables,
            'figures' => $figures,
            'renderedChapters' => $renderedChapters,
            'bibliography' => $bibliography,
            'bibliographyPage' => $bibliographyPage,
        ])->render();
    }

    /**
     * Recursively compile a chapter node and all its child sections.
     *
     * @param Collection<int|string, Collection<int, Chapter>> $groupedChapters
     * @param Collection<int, Reference> $projectReferences
     * @param array<int, array{id: int, title: string, level: int, page: int|null}> $toc
     * @param array<int, array{title: string, chapter: string, page?: int}> $tables
     * @param array<int, array{title: string, chapter: string, page?: int}> $figures
     * @param array<int, array{id: int, title: string, level: int, html: string}> $renderedChapters
     * @param array<int, int> $citedReferenceIds
     */
    protected function compileChapterTree(
        Chapter $chapter,
        int $depth,
        Collection $groupedChapters,
        Collection $projectReferences,
        array &$toc,
        array &$tables,
        array &$figures,
        array &$renderedChapters,
        array &$citedReferenceIds
    ): void {
        $toc[] = [
            'id' => $chapter->id,
            'title' => $chapter->title,
            'level' => $depth,
            'page' => null,
        ];

        $html = '';
        $headingLevel = min($depth, 3);
        $html .= "<h{$headingLevel} class=\"chapter-heading-{$headingLevel}\">".e($chapter->title)."</h{$headingLevel}>\n";

        if (! empty($chapter->content)) {
            $html .= $this->renderTiptapToHtml(
                $chapter->content,
                $chapter->title,
                $headingLevel + 1,
                $projectReferences,
                $tables,
                $figures,
                $citedReferenceIds
            );
        }

        $renderedChapters[] = [
            'id' => $chapter->id,
            'title' => $chapter->title,
            'level' => $depth,
            'html' => $html,
        ];

        $childSections = $groupedChapters->get($chapter->id) ?? collect();
        foreach ($childSections as $child) {
            $this->compileChapterTree(
                $child,
                $depth + 1,
                $groupedChapters,
                $projectReferences,
                $toc,
                $tables,
                $figures,
                $renderedChapters,
                $citedReferenceIds
            );
        }
    }

    /**
     * Render Tiptap JSON node structure to semantic HTML and extract figures/tables/citations.
     *
     * @param array<string, mixed> $content
     * @param Collection<int, Reference> $projectReferences
     * @param array<int, array{title: string, chapter: string, page?: int}> $tables
     * @param array<int, array{title: string, chapter: string, page?: int}> $figures
     * @param array<int, int> $citedReferenceIds
     */
    protected function renderTiptapToHtml(
        array $content,
        string $chapterTitle,
        int $baseHeadingLevel,
        Collection $projectReferences,
        array &$tables,
        array &$figures,
        array &$citedReferenceIds
    ): string {
        $nodes = $content['content'] ?? [];
        if (! is_array($nodes)) {
            return '';
        }

        $output = '';

        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';

            switch ($type) {
                case 'paragraph':
                    $inner = $this->renderInlineNodes($node['content'] ?? [], $projectReferences, $citedReferenceIds);
                    if (trim($inner) !== '') {
                        $output .= "<p class=\"thesis-p\">{$inner}</p>\n";
                    }
                    break;

                case 'heading':
                    $level = min(($node['attrs']['level'] ?? 1) + ($baseHeadingLevel - 1), 3);
                    $headingText = $this->extractPlainText($node['content'] ?? []);
                    if (! empty($headingText)) {
                        $output .= "<h{$level} class=\"chapter-heading-{$level}\">".e($headingText)."</h{$level}>\n";
                    }
                    break;

                case 'bulletList':
                    $output .= "<ul>\n";
                    foreach ($node['content'] ?? [] as $item) {
                        $output .= $this->renderListItem($item, $projectReferences, $citedReferenceIds);
                    }
                    $output .= "</ul>\n";
                    break;

                case 'orderedList':
                    $output .= "<ol>\n";
                    foreach ($node['content'] ?? [] as $item) {
                        $output .= $this->renderListItem($item, $projectReferences, $citedReferenceIds);
                    }
                    $output .= "</ol>\n";
                    break;

                case 'table':
                    $output .= "<table class=\"thesis-table\">\n<tbody>\n";
                    foreach ($node['content'] ?? [] as $row) {
                        if (($row['type'] ?? '') === 'tableRow') {
                            $output .= "<tr>\n";
                            foreach ($row['content'] ?? [] as $cell) {
                                $isHeader = ($cell['type'] ?? '') === 'tableHeader';
                                $tag = $isHeader ? 'th' : 'td';
                                $cellInner = '';
                                foreach ($cell['content'] ?? [] as $cellBlock) {
                                    if (($cellBlock['type'] ?? '') === 'paragraph') {
                                        $cellInner .= $this->renderInlineNodes($cellBlock['content'] ?? [], $projectReferences, $citedReferenceIds);
                                    }
                                }
                                $output .= "<{$tag}>{$cellInner}</{$tag}>\n";
                            }
                            $output .= "</tr>\n";
                        }
                    }
                    $output .= "</tbody>\n</table>\n";
                    break;

                case 'figureCaption':
                    $caption = $this->extractPlainText($node['content'] ?? []);
                    if (! empty($caption)) {
                        $isTableCaption = stripos($caption, 'tabel') !== false;
                        $class = $isTableCaption ? 'table-caption' : 'figure-caption';
                        $output .= "<p class=\"{$class}\">".e($caption)."</p>\n";

                        if ($isTableCaption) {
                            $tables[] = [
                                'title' => $caption,
                                'chapter' => $chapterTitle,
                            ];
                        } else {
                            $figures[] = [
                                'title' => $caption,
                                'chapter' => $chapterTitle,
                            ];
                        }
                    }
                    break;

                case 'image':
                    $src = $node['attrs']['src'] ?? '';
                    $alt = $node['attrs']['alt'] ?? '';
                    if (! empty($src)) {
                        $output .= '<div style="text-align: center; margin: 12pt 0;"><img src="'.e($src).'" alt="'.e($alt).'" style="max-width: 100%; height: auto;" /></div>'."\n";
                    }
                    break;

                case 'blockquote':
                    $quoteText = '';
                    foreach ($node['content'] ?? [] as $block) {
                        if (($block['type'] ?? '') === 'paragraph') {
                            $quoteText .= '<p>'.$this->renderInlineNodes($block['content'] ?? [], $projectReferences, $citedReferenceIds).'</p>';
                        }
                    }
                    $output .= "<blockquote style=\"border-left: 3px solid #9ca3af; padding-left: 1rem; margin: 12pt 0; font-style: italic;\">{$quoteText}</blockquote>\n";
                    break;
            }
        }

        return $output;
    }

    /**
     * Render inline nodes to HTML string.
     *
     * @param array<int, array<string, mixed>> $inlineNodes
     * @param Collection<int, Reference> $projectReferences
     * @param array<int, int> $citedReferenceIds
     */
    protected function renderInlineNodes(array $inlineNodes, Collection $projectReferences, array &$citedReferenceIds): string
    {
        $out = '';
        foreach ($inlineNodes as $child) {
            $type = $child['type'] ?? '';

            if ($type === 'text') {
                $text = e($child['text'] ?? '');
                if (! empty($child['marks'])) {
                    foreach ($child['marks'] as $mark) {
                        $markType = $mark['type'] ?? '';
                        match ($markType) {
                            'bold' => $text = "<strong>{$text}</strong>",
                            'italic' => $text = "<em>{$text}</em>",
                            'underline' => $text = "<u>{$text}</u>",
                            'strike' => $text = "<s>{$text}</s>",
                            'code' => $text = "<code>{$text}</code>",
                            default => null,
                        };
                    }
                }
                $out .= $text;
            } elseif ($type === 'citation') {
                $refId = (int) ($child['attrs']['reference_id'] ?? 0);
                if ($refId > 0) {
                    $citedReferenceIds[] = $refId;
                }

                $label = $child['attrs']['label'] ?? '';
                if (empty($label) && $refId > 0) {
                    $ref = $projectReferences->get($refId);
                    $label = $ref ? $ref->in_text_citation : '(Sitasi)';
                }
                $out .= '<span class="citation">'.e($label ?: '(Sitasi)').'</span>';
            } elseif ($type === 'checkedClaim') {
                $claimText = $child['attrs']['claim_text'] ?? '';
                $out .= '<span class="checked-claim">'.e($claimText).'</span>';
            } elseif ($type === 'hardBreak') {
                $out .= '<br>';
            }
        }

        return $out;
    }

    /**
     * Render list item content.
     *
     * @param array<string, mixed> $itemNode
     * @param Collection<int, Reference> $projectReferences
     * @param array<int, int> $citedReferenceIds
     */
    protected function renderListItem(array $itemNode, Collection $projectReferences, array &$citedReferenceIds): string
    {
        $inner = '';
        foreach ($itemNode['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'paragraph') {
                $inner .= $this->renderInlineNodes($block['content'] ?? [], $projectReferences, $citedReferenceIds);
            }
        }

        return "<li>{$inner}</li>\n";
    }

    /**
     * Extract plain text representation from nested Tiptap content nodes.
     *
     * @param array<int, mixed>|mixed $nodes
     */
    protected function extractPlainText(mixed $nodes): string
    {
        if (empty($nodes)) {
            return '';
        }

        $text = '';
        $traverse = function ($item) use (&$traverse, &$text) {
            if (! is_array($item)) {
                return;
            }

            if (isset($item['type'])) {
                if ($item['type'] === 'text' && isset($item['text'])) {
                    $text .= $item['text'];
                } elseif ($item['type'] === 'citation' && isset($item['attrs']['label'])) {
                    $text .= $item['attrs']['label'];
                }
            }

            foreach ($item as $val) {
                if (is_array($val)) {
                    $traverse($val);
                }
            }
        };

        $traverse($nodes);

        return trim($text);
    }

    /**
     * Compile bibliography list formatted by project citation style for cited references only.
     *
     * @param Collection<int, Reference> $projectReferences
     * @param array<int, int> $citedReferenceIds
     * @return array<int, string>
     */
    protected function compileBibliography(Project $project, Collection $projectReferences, array $citedReferenceIds): array
    {
        $citedReferences = $projectReferences->whereIn('id', $citedReferenceIds);
        if ($citedReferences->isEmpty()) {
            return [];
        }

        $sortedReferences = $citedReferences->sort(function (Reference $a, Reference $b) {
            $authorA = ! empty($a->authors[0]['family']) ? strtolower($a->authors[0]['family']) : strtolower($a->title);
            $authorB = ! empty($b->authors[0]['family']) ? strtolower($b->authors[0]['family']) : strtolower($b->title);
            if ($authorA === $authorB) {
                return ($a->year ?? 0) <=> ($b->year ?? 0);
            }

            return strcmp($authorA, $authorB);
        });

        $style = $project->citation_style ?? 'apa';
        $entries = [];

        foreach ($sortedReferences as $reference) {
            $entries[] = CslFormatter::format($reference, $style);
        }

        return $entries;
    }

    /**
     * Resolve formatting specifications from project guideline profile or standard defaults.
     *
     * @return array<string, mixed>
     */
    protected function resolveFormatting(Project $project): array
    {
        $guidelineProfile = $project->guideline_profile ?? [];

        return $guidelineProfile['formatting'] ?? [
            'font_family' => 'Times New Roman',
            'font_size' => '12pt',
            'line_spacing' => '1.5',
            'paper_size' => 'A4',
            'margins' => [
                'top' => '4cm',
                'left' => '4cm',
                'bottom' => '3cm',
                'right' => '3cm',
            ],
        ];
    }

    /**
     * Resolve headless chromium binary location.
     *
     * @throws RuntimeException
     */
    protected function resolveChromiumBinary(): string
    {
        $configured = config('services.chromium.binary') ?? env('CHROMIUM_BINARY');
        if ($configured && file_exists($configured) && is_executable($configured)) {
            return $configured;
        }

        $candidates = [
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/helium-browser',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        // Check PATH via which
        $whichProcess = Process::run(['which', 'chromium']);
        if ($whichProcess->successful() && trim($whichProcess->output()) !== '') {
            return trim($whichProcess->output());
        }

        $whichChrome = Process::run(['which', 'google-chrome']);
        if ($whichChrome->successful() && trim($whichChrome->output()) !== '') {
            return trim($whichChrome->output());
        }

        throw new RuntimeException('Headless Chromium binary tidak ditemukan. Silakan pasang Chromium atau konfigurasikan CHROMIUM_BINARY.');
    }
}
