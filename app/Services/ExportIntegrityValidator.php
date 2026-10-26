<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\Project;

class ExportIntegrityValidator
{
    /**
     * Run pre-export integrity validation across all project chapters and library references.
     *
     * @return array{
     *     is_valid: bool,
     *     summary: array{
     *         empty_chapters_count: int,
     *         uncited_references_count: int,
     *         invalid_citations_count: int,
     *         total_warnings_count: int
     *     },
     *     empty_chapters: array<int, array{id: int, title: string, parent_id: ?int}>,
     *     uncited_references: array<int, array{id: int, title: string, authors: ?array, year: ?int}>,
     *     invalid_citations: array<int, array{chapter_id: int, chapter_title: string, reference_id: ?int}>,
     *     warnings: array<int, string>
     * }
     */
    public function validate(Project $project): array
    {
        $allChapters = $project->chapters()->get();
        $projectReferences = $project->references()->get();
        $validRefIds = $projectReferences->pluck('id')->all();

        $emptyChapters = [];
        $invalidCitations = [];
        $citedRefIds = [];

        foreach ($allChapters as $chapter) {
            // Check for empty chapters
            // If leaf chapter (no sub-sections) or standalone chapter
            $hasChildSections = $allChapters->contains('parent_id', $chapter->id);
            if (! $hasChildSections && self::isContentEmpty($chapter->content)) {
                $emptyChapters[] = [
                    'id' => $chapter->id,
                    'title' => $chapter->title,
                    'parent_id' => $chapter->parent_id,
                ];
            }

            // Extract all citation nodes from chapter content
            $citations = self::extractCitationNodes($chapter->content);
            foreach ($citations as $cit) {
                $refId = $cit['reference_id'] ?? null;
                if ($refId === null || ! in_array($refId, $validRefIds, true)) {
                    $invalidCitations[] = [
                        'chapter_id' => $chapter->id,
                        'chapter_title' => $chapter->title,
                        'reference_id' => $refId,
                    ];
                } else {
                    $citedRefIds[] = $refId;
                }
            }
        }

        $uniqueCitedRefIds = array_unique($citedRefIds);

        // Check for uncited references in the project Research Library
        $uncitedReferences = [];
        foreach ($projectReferences as $ref) {
            if (! in_array($ref->id, $uniqueCitedRefIds, true)) {
                $uncitedReferences[] = [
                    'id' => $ref->id,
                    'title' => $ref->title,
                    'authors' => $ref->authors,
                    'year' => $ref->year,
                ];
            }
        }

        // Build human-readable warning messages
        $warnings = [];
        foreach ($emptyChapters as $ec) {
            $warnings[] = "Bab atau seksi '{$ec['title']}' masih kosong belum memiliki teks.";
        }
        foreach ($uncitedReferences as $ur) {
            $warnings[] = "Referensi '{$ur['title']}' belum disitasi di bab manapun.";
        }
        foreach ($invalidCitations as $ic) {
            if ($ic['reference_id'] === null) {
                $warnings[] = "Sitasi tanpa ID referensi pada '{$ic['chapter_title']}' tidak valid.";
            } else {
                $warnings[] = "Sitasi dengan ID {$ic['reference_id']} pada '{$ic['chapter_title']}' tidak ditemukan di Research Library.";
            }
        }

        $totalWarnings = count($emptyChapters) + count($uncitedReferences) + count($invalidCitations);

        return [
            'is_valid' => $totalWarnings === 0,
            'summary' => [
                'empty_chapters_count' => count($emptyChapters),
                'uncited_references_count' => count($uncitedReferences),
                'invalid_citations_count' => count($invalidCitations),
                'total_warnings_count' => $totalWarnings,
            ],
            'empty_chapters' => $emptyChapters,
            'uncited_references' => $uncitedReferences,
            'invalid_citations' => $invalidCitations,
            'warnings' => $warnings,
        ];
    }

    /**
     * Determine if a Tiptap content document array is empty or contains only whitespace.
     */
    public static function isContentEmpty(?array $content): bool
    {
        if (empty($content)) {
            return true;
        }

        $text = '';
        $hasNonTextContent = false;

        $traverse = function ($nodes) use (&$traverse, &$text, &$hasNonTextContent) {
            if (! is_array($nodes)) {
                return;
            }

            if (isset($nodes['type']) && in_array($nodes['type'], ['citation', 'image', 'figureCaption', 'table', 'checkedClaim'], true)) {
                $hasNonTextContent = true;
            }

            if (isset($nodes['text']) && is_string($nodes['text'])) {
                $text .= $nodes['text'];
            }

            if (isset($nodes['content']) && is_array($nodes['content'])) {
                $traverse($nodes['content']);
            } else {
                foreach ($nodes as $child) {
                    if (is_array($child)) {
                        $traverse($child);
                    }
                }
            }
        };

        $traverse($content);

        if ($hasNonTextContent) {
            return false;
        }

        return trim($text) === '';
    }

    /**
     * Extract all citation node attributes from a Tiptap document.
     *
     * @return array<int, array{reference_id: ?int, label: ?string}>
     */
    public static function extractCitationNodes(?array $content): array
    {
        if (empty($content)) {
            return [];
        }

        $citations = [];
        $traverse = function ($nodes) use (&$traverse, &$citations) {
            if (! is_array($nodes)) {
                return;
            }

            foreach ($nodes as $node) {
                if (! is_array($node)) {
                    continue;
                }

                if (($node['type'] ?? '') === 'citation') {
                    $refId = isset($node['attrs']['reference_id']) && $node['attrs']['reference_id'] !== ''
                        ? (int) $node['attrs']['reference_id']
                        : null;

                    $citations[] = [
                        'reference_id' => $refId,
                        'label' => $node['attrs']['label'] ?? null,
                    ];
                }

                if (isset($node['content']) && is_array($node['content'])) {
                    $traverse($node['content']);
                }
            }
        };

        $traverse($content['content'] ?? []);

        return $citations;
    }
}
