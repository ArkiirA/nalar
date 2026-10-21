<?php

namespace App\Models;

use App\Support\CslFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Reference extends Model
{
    /** @use HasFactory<\Database\Factories\ReferenceFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'document_id',
        'reference_key',
        'type',
        'title',
        'authors',
        'year',
        'publication_title',
        'volume',
        'issue',
        'pages',
        'publisher',
        'publisher_place',
        'doi',
        'url',
        'csl_data',
        'is_confirmed',
    ];

    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'csl_data' => 'array',
            'is_confirmed' => 'boolean',
            'year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reference $reference) {
            if (empty($reference->reference_key)) {
                $reference->reference_key = $reference->generateReferenceKey();
            }
        });

        static::saving(function (Reference $reference) {
            // Keep structured CSL-JSON representation synchronized
            $reference->csl_data = $reference->toCslJson();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Generate a stable reference key slug e.g. "wijaya2023" or "smith2024_1".
     */
    public function generateReferenceKey(): string
    {
        $authors = $this->authors ?? [];
        $family = 'ref';
        if (! empty($authors[0])) {
            $first = $authors[0];
            $family = is_string($first)
                ? $first
                : ($first['family'] ?? $first['literal'] ?? 'ref');
        }

        $base = Str::slug($family) ?: 'ref';
        $year = $this->year ?? date('Y');
        $key = "{$base}{$year}";

        if ($this->project_id) {
            $exists = static::where('project_id', $this->project_id)
                ->where('reference_key', $key)
                ->when($this->id, fn ($q) => $q->where('id', '!=', $this->id))
                ->exists();

            if ($exists) {
                $suffix = 1;
                while (static::where('project_id', $this->project_id)->where('reference_key', "{$key}_{$suffix}")->exists()) {
                    $suffix++;
                }
                $key = "{$key}_{$suffix}";
            }
        }

        return $key;
    }

    /**
     * Normalize author input to structured CSL array.
     *
     * @param mixed $authors
     * @return array<int, array{family: string, given?: string, literal?: string}>
     */
    public static function normalizeAuthors(mixed $authors): array
    {
        if (empty($authors)) {
            return [];
        }

        if (is_string($authors)) {
            $parts = explode(';', $authors);
            $normalized = [];
            foreach ($parts as $part) {
                $trimmed = trim($part);
                if (! empty($trimmed)) {
                    if (str_contains($trimmed, ',')) {
                        // "Last, First" format
                        [$family, $given] = explode(',', $trimmed, 2);
                        $normalized[] = ['family' => trim($family), 'given' => trim($given)];
                    } else {
                        // "First Last" format
                        $words = explode(' ', $trimmed);
                        if (count($words) > 1) {
                            $family = array_pop($words);
                            $given = implode(' ', $words);
                            $normalized[] = ['family' => $family, 'given' => $given];
                        } else {
                            $normalized[] = ['family' => $trimmed, 'given' => ''];
                        }
                    }
                }
            }

            return $normalized;
        }

        if (is_array($authors)) {
            $normalized = [];
            foreach ($authors as $item) {
                if (is_string($item)) {
                    $normalized[] = ['family' => trim($item), 'given' => ''];
                } elseif (is_array($item)) {
                    $normalized[] = [
                        'family' => trim($item['family'] ?? ''),
                        'given' => trim($item['given'] ?? ''),
                        'literal' => isset($item['literal']) ? trim($item['literal']) : null,
                    ];
                }
            }

            return $normalized;
        }

        return [];
    }

    /**
     * Convert this model into standard CSL-JSON representation.
     */
    public function toCslJson(): array
    {
        $csl = [
            'id' => (string) ($this->reference_key ?: $this->id),
            'type' => $this->type ?: 'article-journal',
            'title' => $this->title,
        ];

        if (! empty($this->authors)) {
            $csl['author'] = $this->authors;
        }

        if ($this->year) {
            $csl['issued'] = [
                'date-parts' => [[(int) $this->year]],
            ];
        }

        if ($this->publication_title) {
            $csl['container-title'] = $this->publication_title;
        }

        if ($this->volume) {
            $csl['volume'] = $this->volume;
        }

        if ($this->issue) {
            $csl['issue'] = $this->issue;
        }

        if ($this->pages) {
            $csl['page'] = $this->pages;
        }

        if ($this->publisher) {
            $csl['publisher'] = $this->publisher;
        }

        if ($this->publisher_place) {
            $csl['publisher-place'] = $this->publisher_place;
        }

        if ($this->doi) {
            $csl['DOI'] = $this->doi;
        }

        if ($this->url) {
            $csl['URL'] = $this->url;
        }

        return array_merge($this->csl_data ?? [], $csl);
    }

    /**
     * Extract reference model attributes from a standard CSL-JSON array.
     */
    public static function fromCslJson(array $csl): array
    {
        $authors = $csl['author'] ?? [];

        $year = null;
        if (! empty($csl['issued']['date-parts'][0][0])) {
            $year = (int) $csl['issued']['date-parts'][0][0];
        }

        return [
            'type' => $csl['type'] ?? 'article-journal',
            'title' => $csl['title'] ?? '',
            'authors' => $authors,
            'year' => $year,
            'publication_title' => $csl['container-title'] ?? null,
            'volume' => isset($csl['volume']) ? (string) $csl['volume'] : null,
            'issue' => isset($csl['issue']) ? (string) $csl['issue'] : null,
            'pages' => $csl['page'] ?? null,
            'publisher' => $csl['publisher'] ?? null,
            'publisher_place' => $csl['publisher-place'] ?? null,
            'doi' => $csl['DOI'] ?? $csl['doi'] ?? null,
            'url' => $csl['URL'] ?? $csl['url'] ?? null,
            'csl_data' => $csl,
        ];
    }

    /**
     * Detect missing fields according to bibliographic type and return warning messages.
     *
     * @return array<string, string>
     */
    public function getMissingFieldWarnings(): array
    {
        $warnings = [];

        if (empty($this->authors)) {
            $warnings['authors'] = 'Nama penulis belum dicantumkan.';
        }

        if (empty($this->year)) {
            $warnings['year'] = 'Tahun publikasi belum diisi.';
        }

        switch ($this->type) {
            case 'article-journal':
                if (empty($this->publication_title)) {
                    $warnings['publication_title'] = 'Nama jurnal ilmiah belum diisi.';
                }
                if (empty($this->volume)) {
                    $warnings['volume'] = 'Nomor volume jurnal belum diisi.';
                }
                if (empty($this->pages)) {
                    $warnings['pages'] = 'Rentang halaman artikel belum diisi.';
                }
                break;

            case 'book':
                if (empty($this->publisher)) {
                    $warnings['publisher'] = 'Nama penerbit buku belum diisi.';
                }
                break;

            case 'paper-conference':
                if (empty($this->publication_title)) {
                    $warnings['publication_title'] = 'Nama prosiding atau konferensi belum diisi.';
                }
                break;
        }

        return $warnings;
    }

    /**
     * Formatted string in project or preferred style.
     */
    public function getFormattedAttribute(): string
    {
        $style = $this->project?->citation_style ?? 'apa';

        return CslFormatter::format($this, $style);
    }

    public function getFormattedApaAttribute(): string
    {
        return CslFormatter::formatApa7($this);
    }

    public function getFormattedHarvardAttribute(): string
    {
        return CslFormatter::formatHarvard($this);
    }

    public function getInTextCitationAttribute(): string
    {
        $style = $this->project?->citation_style ?? 'apa';

        return CslFormatter::inTextCitation($this, $style);
    }

    /**
     * Determine if this reference is currently cited in any project chapter or section.
     */
    public function isCited(): bool
    {
        if (! $this->project_id) {
            return false;
        }

        return Chapter::isReferenceCited($this->project_id, $this->id);
    }
}
