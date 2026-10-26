<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;
use App\Models\Reference;
use App\Support\CslFormatter;
use Illuminate\Support\Collection;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class DocxExportService
{
    /**
     * Compile project thesis outline and chapters into a styled DOCX file.
     *
     * @return string Absolute path to the generated .docx file.
     */
    public function generate(Project $project): string
    {
        $phpWord = new PhpWord();

        // 1. Resolve formatting from guideline profile or defaults
        $formatting = $this->resolveFormatting($project);

        $fontName = $formatting['font_family'] ?? 'Times New Roman';
        $fontSize = (int) filter_var($formatting['font_size'] ?? '12', FILTER_SANITIZE_NUMBER_INT) ?: 12;
        $lineSpacing = (float) ($formatting['line_spacing'] ?? 1.5);

        // Configure default document font
        $phpWord->setDefaultFontName($fontName);
        $phpWord->setDefaultFontSize($fontSize);

        // 2. Define standard paragraph styles
        $phpWord->addParagraphStyle('ThesisParagraph', [
            'lineHeight' => $lineSpacing,
            'spaceAfter' => (int) round(Converter::pointToTwip(6)),
            'alignment' => Jc::BOTH,
        ]);

        $phpWord->addParagraphStyle('BibliographyStyle', [
            'lineHeight' => $lineSpacing,
            'spaceAfter' => (int) round(Converter::pointToTwip(6)),
            'alignment' => Jc::BOTH,
            'indentation' => ['hanging' => (int) round(Converter::cmToTwip(1.27))],
        ]);

        // 3. Define heading styles
        $phpWord->addTitleStyle(1, [
            'name' => $fontName,
            'size' => $fontSize + 2,
            'bold' => true,
        ], [
            'alignment' => Jc::CENTER,
            'spaceBefore' => 0,
            'spaceAfter' => (int) round(Converter::pointToTwip(12)),
            'lineHeight' => $lineSpacing,
        ]);

        $phpWord->addTitleStyle(2, [
            'name' => $fontName,
            'size' => $fontSize,
            'bold' => true,
        ], [
            'alignment' => Jc::LEFT,
            'spaceBefore' => (int) round(Converter::pointToTwip(12)),
            'spaceAfter' => (int) round(Converter::pointToTwip(6)),
            'lineHeight' => $lineSpacing,
        ]);

        $phpWord->addTitleStyle(3, [
            'name' => $fontName,
            'size' => $fontSize,
            'bold' => true,
            'italic' => true,
        ], [
            'alignment' => Jc::LEFT,
            'spaceBefore' => (int) round(Converter::pointToTwip(6)),
            'spaceAfter' => (int) round(Converter::pointToTwip(4)),
            'lineHeight' => $lineSpacing,
        ]);

        // 4. Section margins: configured or Indonesian standard (Top 4cm, Left 4cm, Bottom 3cm, Right 3cm)
        $margins = $formatting['margins'] ?? [];
        $topCm = $this->parseMarginCm($margins['top'] ?? null, 4.0);
        $leftCm = $this->parseMarginCm($margins['left'] ?? null, 4.0);
        $bottomCm = $this->parseMarginCm($margins['bottom'] ?? null, 3.0);
        $rightCm = $this->parseMarginCm($margins['right'] ?? null, 3.0);

        $sectionStyle = [
            'paperSize' => $formatting['paper_size'] ?? 'A4',
            'marginTop' => (int) round(Converter::cmToTwip($topCm)),
            'marginLeft' => (int) round(Converter::cmToTwip($leftCm)),
            'marginBottom' => (int) round(Converter::cmToTwip($bottomCm)),
            'marginRight' => (int) round(Converter::cmToTwip($rightCm)),
        ];

        $section = $phpWord->addSection($sectionStyle);

        // 5. Pre-index project references strictly within tenant boundary to prevent cross-project leakage & N+1 queries
        $projectReferences = $project->references()->get()->keyBy('id');

        // 6. Sequential chapter stitching in full outline tree order
        $allChapters = $project->chapters()->orderBy('order')->get()->groupBy('parent_id');
        $rootChapters = $allChapters->get(null) ?? collect();
        $isFirstChapter = true;

        foreach ($rootChapters as $rootChapter) {
            if (! $isFirstChapter) {
                // Chapter page break between major chapters
                $section->addPageBreak();
            }
            $isFirstChapter = false;

            $this->renderChapterNode($section, $rootChapter, 1, $allChapters, $projectReferences);
        }

        // 7. Generate Bibliography Section (Daftar Pustaka)
        $this->appendBibliography($section, $project, $projectReferences);

        // 8. Save to temporary file
        $tempDir = config('filesystems.disks.local.root', storage_path('app'));
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0755, true);
        }
        $filename = 'export_'.uniqid().'.docx';
        $filePath = rtrim($tempDir, '/').'/'.$filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Recursively render a chapter node and all its child sections in outline order.
     *
     * @param Collection<int|string, Collection<int, Chapter>> $groupedChapters
     * @param Collection<int, Reference> $projectReferences
     */
    protected function renderChapterNode(
        Section $section,
        Chapter $chapter,
        int $depth,
        Collection $groupedChapters,
        Collection $projectReferences
    ): void {
        $headingLevel = min($depth, 3);
        $section->addTitle($chapter->title, $headingLevel);

        if (! empty($chapter->content)) {
            $this->renderTiptapContent($section, $chapter->content, $headingLevel + 1, $projectReferences);
        }

        $childSections = $groupedChapters->get($chapter->id) ?? collect();
        foreach ($childSections as $child) {
            $this->renderChapterNode($section, $child, $depth + 1, $groupedChapters, $projectReferences);
        }
    }

    /**
     * Render Tiptap JSON content into PHPWord section elements.
     *
     * @param array<string, mixed> $content
     * @param Collection<int, Reference> $projectReferences
     */
    protected function renderTiptapContent(
        Section $section,
        array $content,
        int $baseHeadingLevel,
        Collection $projectReferences
    ): void {
        $nodes = $content['content'] ?? [];
        if (! is_array($nodes)) {
            return;
        }

        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';

            switch ($type) {
                case 'paragraph':
                    $textRun = $section->addTextRun('ThesisParagraph');
                    $this->renderInlineElements($textRun, $node['content'] ?? [], $projectReferences);
                    break;

                case 'heading':
                    $level = ($node['attrs']['level'] ?? 1) + ($baseHeadingLevel - 1);
                    $headingText = $this->extractPlainText($node['content'] ?? []);
                    if (! empty($headingText)) {
                        $section->addTitle($headingText, min($level, 3));
                    }
                    break;

                case 'bulletList':
                    $listItems = $node['content'] ?? [];
                    foreach ($listItems as $item) {
                        $itemText = $this->extractPlainText($item['content'] ?? []);
                        if (! empty($itemText)) {
                            $section->addListItem($itemText, 0, null, \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED, 'ThesisParagraph');
                        }
                    }
                    break;

                case 'orderedList':
                    $listItems = $node['content'] ?? [];
                    foreach ($listItems as $item) {
                        $itemText = $this->extractPlainText($item['content'] ?? []);
                        if (! empty($itemText)) {
                            $section->addListItem($itemText, 0, null, \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER, 'ThesisParagraph');
                        }
                    }
                    break;

                case 'blockquote':
                    $quoteText = $this->extractPlainText($node['content'] ?? []);
                    if (! empty($quoteText)) {
                        $section->addText($quoteText, ['italic' => true], [
                            'indentation' => ['left' => (int) round(Converter::cmToTwip(1))],
                            'lineHeight' => 1.5,
                        ]);
                    }
                    break;

                case 'table':
                    $table = $section->addTable(['borderSize' => 6, 'borderColor' => '999999']);
                    $rows = $node['content'] ?? [];
                    foreach ($rows as $row) {
                        $table->addRow();
                        $cells = $row['content'] ?? [];
                        foreach ($cells as $cell) {
                            $cellText = $this->extractPlainText($cell['content'] ?? []);
                            $table->addCell((int) round(Converter::cmToTwip(4)))->addText($cellText, [], 'ThesisParagraph');
                        }
                    }
                    break;

                case 'figureCaption':
                    $captionText = $this->extractPlainText($node['content'] ?? []);
                    if (! empty($captionText)) {
                        $section->addText($captionText, ['italic' => true], [
                            'alignment' => Jc::CENTER,
                            'lineHeight' => 1.5,
                        ]);
                    }
                    break;
            }
        }
    }

    /**
     * Render inline children (text with marks, citations) into a TextRun.
     *
     * @param array<int, array<string, mixed>> $inlineNodes
     * @param Collection<int, Reference> $projectReferences
     */
    protected function renderInlineElements($textRun, array $inlineNodes, Collection $projectReferences): void
    {
        foreach ($inlineNodes as $child) {
            $type = $child['type'] ?? '';

            if ($type === 'text') {
                $text = $child['text'] ?? '';
                $font = [];
                if (! empty($child['marks'])) {
                    foreach ($child['marks'] as $mark) {
                        match ($mark['type'] ?? '') {
                            'bold' => $font['bold'] = true,
                            'italic' => $font['italic'] = true,
                            'strike' => $font['strikethrough'] = true,
                            'underline' => $font['underline'] = 'single',
                            default => null,
                        };
                    }
                }
                $textRun->addText($text, $font);
            } elseif ($type === 'citation') {
                $label = $child['attrs']['label'] ?? '';
                if (empty($label) && ! empty($child['attrs']['reference_id'])) {
                    // Tenant scoped reference resolution
                    $ref = $projectReferences->get((int) $child['attrs']['reference_id']);
                    $label = $ref ? $ref->in_text_citation : '(Sitasi)';
                }
                $textRun->addText($label, []);
            } elseif ($type === 'checkedClaim') {
                $claimText = $child['attrs']['claim_text'] ?? '';
                $textRun->addText($claimText, []);
            } elseif ($type === 'hardBreak') {
                $textRun->addTextBreak();
            }
        }
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
                } elseif ($item['type'] === 'checkedClaim' && isset($item['attrs']['claim_text'])) {
                    $text .= $item['attrs']['claim_text'];
                }
            }

            if (isset($item['content']) && is_array($item['content'])) {
                $traverse($item['content']);
            } else {
                foreach ($item as $sub) {
                    if (is_array($sub)) {
                        $traverse($sub);
                    }
                }
            }
        };

        $traverse($nodes);

        return trim($text);
    }

    /**
     * Append formatted bibliography section for cited references only.
     *
     * @param Collection<int, Reference> $projectReferences
     */
    protected function appendBibliography(Section $section, Project $project, Collection $projectReferences): void
    {
        $allChapters = $project->chapters()->get();
        $citedIds = [];
        foreach ($allChapters as $chapter) {
            $citedIds = array_merge($citedIds, Chapter::extractCitationReferenceIds($chapter->content));
        }
        $citedIds = array_values(array_unique($citedIds));

        // Citation-to-bibliography consistency: strictly include only cited references
        if (empty($citedIds)) {
            return;
        }

        $citedReferences = $projectReferences->whereIn('id', $citedIds);
        if ($citedReferences->isEmpty()) {
            return;
        }

        // Bibliography starts on new page
        $section->addPageBreak();
        $section->addTitle('DAFTAR PUSTAKA', 1);

        // Sort alphabetically by primary author family name or title, then year
        $sortedReferences = $citedReferences->sort(function (Reference $a, Reference $b) {
            $authorA = ! empty($a->authors[0]['family']) ? strtolower($a->authors[0]['family']) : strtolower($a->title);
            $authorB = ! empty($b->authors[0]['family']) ? strtolower($b->authors[0]['family']) : strtolower($b->title);
            if ($authorA === $authorB) {
                return ($a->year ?? 0) <=> ($b->year ?? 0);
            }

            return strcmp($authorA, $authorB);
        });

        $style = $project->citation_style ?? 'apa';

        foreach ($sortedReferences as $reference) {
            $formattedEntry = CslFormatter::format($reference, $style);
            $section->addText($formattedEntry, ['name' => 'Times New Roman', 'size' => 12], 'BibliographyStyle');
        }
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
                'top' => '4 cm',
                'left' => '4 cm',
                'bottom' => '3 cm',
                'right' => '3 cm',
            ],
        ];
    }

    /**
     * Parse numeric centimeter margin value from string or float.
     */
    protected function parseMarginCm(mixed $margin, float $fallback): float
    {
        if (empty($margin)) {
            return $fallback;
        }

        if (is_numeric($margin)) {
            return (float) $margin;
        }

        if (preg_match('/([\d\.]+)\s*cm/i', (string) $margin, $matches)) {
            return (float) $matches[1];
        }

        return $fallback;
    }
}
