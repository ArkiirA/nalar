<?php

namespace App\Support;

use App\Models\Reference;

class CslFormatter
{
    /**
     * Format a reference according to the specified style.
     */
    public static function format(Reference $reference, ?string $style = 'apa'): string
    {
        $style = strtolower($style ?? 'apa');

        if (str_contains($style, 'harvard')) {
            return self::formatHarvard($reference);
        }

        return self::formatApa7($reference);
    }

    /**
     * Format reference in APA 7th Edition style.
     */
    public static function formatApa7(Reference $ref): string
    {
        $authors = self::ensurePeriod(self::formatAuthorsApa($ref->authors ?? []));
        $year = $ref->year ? "({$ref->year})." : '(n.d.).';
        $title = self::ensurePeriod(trim($ref->title));

        $sourceParts = [];

        switch ($ref->type) {
            case 'article-journal':
                if (! empty($ref->publication_title)) {
                    $journal = trim($ref->publication_title);
                    if (! empty($ref->volume)) {
                        $journal .= ', '.$ref->volume;
                        if (! empty($ref->issue)) {
                            $journal .= '('.$ref->issue.')';
                        }
                    }
                    if (! empty($ref->pages)) {
                        $journal .= ', '.$ref->pages;
                    }
                    $sourceParts[] = self::ensurePeriod($journal);
                }
                break;

            case 'book':
                if (! empty($ref->publisher)) {
                    $sourceParts[] = self::ensurePeriod(trim($ref->publisher));
                }
                break;

            case 'paper-conference':
                $conf = '';
                if (! empty($ref->publication_title)) {
                    $conf = 'In '.trim($ref->publication_title);
                    if (! empty($ref->pages)) {
                        $conf .= ' (pp. '.$ref->pages.')';
                    }
                    $conf = self::ensurePeriod($conf);
                }
                if (! empty($conf)) {
                    $sourceParts[] = $conf;
                }
                if (! empty($ref->publisher)) {
                    $sourceParts[] = self::ensurePeriod(trim($ref->publisher));
                }
                break;

            default:
                if (! empty($ref->publication_title)) {
                    $sourceParts[] = self::ensurePeriod(trim($ref->publication_title));
                } elseif (! empty($ref->publisher)) {
                    $sourceParts[] = self::ensurePeriod(trim($ref->publisher));
                }
                break;
        }

        if (! empty($ref->doi)) {
            $doi = trim($ref->doi);
            $doiUrl = str_starts_with($doi, 'http') ? $doi : 'https://doi.org/'.$doi;
            $sourceParts[] = $doiUrl;
        } elseif (! empty($ref->url)) {
            $sourceParts[] = trim($ref->url);
        }

        $line = trim("{$authors} {$year} {$title}");
        if (! empty($sourceParts)) {
            $line .= ' '.implode(' ', $sourceParts);
        }

        return trim($line);
    }

    /**
     * Format reference in Harvard style.
     */
    public static function formatHarvard(Reference $ref): string
    {
        $authors = self::formatAuthorsHarvard($ref->authors ?? []);
        $year = $ref->year ? "({$ref->year})" : '(n.d.)';

        $extra = '';
        if (! empty($ref->doi)) {
            $doi = trim($ref->doi);
            $doiUrl = str_starts_with($doi, 'http') ? $doi : 'https://doi.org/'.$doi;
            $extra = 'Available at: '.$doiUrl.'.';
        } elseif (! empty($ref->url)) {
            $extra = 'Available at: '.trim($ref->url).'.';
        }

        switch ($ref->type) {
            case 'article-journal':
                $title = "'".rtrim(trim($ref->title), '.')."',";
                $journalParts = [];
                if (! empty($ref->publication_title)) {
                    $journal = trim($ref->publication_title);
                    if (! empty($ref->volume)) {
                        $journal .= ', vol. '.$ref->volume;
                    }
                    if (! empty($ref->issue)) {
                        $journal .= ', no. '.$ref->issue;
                    }
                    if (! empty($ref->pages)) {
                        $journal .= ', pp. '.$ref->pages;
                    }
                    $journalParts[] = self::ensurePeriod($journal);
                }
                $source = implode(' ', $journalParts);
                $line = trim("{$authors} {$year} {$title} {$source}");
                break;

            case 'book':
                $title = self::ensurePeriod(trim($ref->title));
                $pubParts = [];
                if (! empty($ref->publisher_place) && ! empty($ref->publisher)) {
                    $pubParts[] = trim($ref->publisher_place).': '.self::ensurePeriod(trim($ref->publisher));
                } elseif (! empty($ref->publisher)) {
                    $pubParts[] = self::ensurePeriod(trim($ref->publisher));
                }
                $pub = implode(' ', $pubParts);
                $line = trim("{$authors} {$year} {$title} {$pub}");
                break;

            case 'paper-conference':
                $title = "'".rtrim(trim($ref->title), '.')."',";
                $conf = '';
                if (! empty($ref->publication_title)) {
                    $conf = 'in '.trim($ref->publication_title);
                    if (! empty($ref->pages)) {
                        $conf .= ', pp. '.$ref->pages;
                    }
                    $conf = self::ensurePeriod($conf);
                }
                $line = trim("{$authors} {$year} {$title} {$conf}");
                break;

            default:
                $title = self::ensurePeriod(trim($ref->title));
                $source = ! empty($ref->publication_title) ? self::ensurePeriod(trim($ref->publication_title)) : '';
                $line = trim("{$authors} {$year} {$title} {$source}");
                break;
        }

        if (! empty($extra)) {
            $line = trim($line.' '.$extra);
        }

        return $line;
    }

    /**
     * Format an in-text citation tag e.g. (Wijaya, 2023) or (Wijaya & Pratama, 2023).
     */
    public static function inTextCitation(Reference $ref, ?string $style = 'apa'): string
    {
        $style = strtolower($style ?? 'apa');
        $isHarvard = str_contains($style, 'harvard');
        $authors = $ref->authors ?? [];
        $year = $ref->year ?? 'n.d.';

        if (empty($authors)) {
            $words = explode(' ', trim($ref->title));
            $shortTitle = implode(' ', array_slice($words, 0, 3));

            return "({$shortTitle}, {$year})";
        }

        $count = count($authors);
        $getFamily = function ($author) {
            if (is_string($author)) {
                return $author;
            }
            if (! empty($author['family'])) {
                return $author['family'];
            }
            if (! empty($author['literal'])) {
                return $author['literal'];
            }

            return $author['given'] ?? 'Anonim';
        };

        if ($count === 1) {
            $authorStr = $getFamily($authors[0]);
        } elseif ($count === 2) {
            $connector = $isHarvard ? ' and ' : ' & ';
            $authorStr = $getFamily($authors[0]).$connector.$getFamily($authors[1]);
        } else {
            $authorStr = $getFamily($authors[0]).' et al.';
        }

        return "({$authorStr}, {$year})";
    }

    /**
     * Format authors for APA 7th.
     */
    protected static function formatAuthorsApa(array $authors): string
    {
        if (empty($authors)) {
            return '';
        }

        $formatted = [];
        foreach ($authors as $author) {
            $formatted[] = self::formatSingleAuthor($author);
        }

        $count = count($formatted);
        if ($count === 1) {
            return $formatted[0];
        }

        if ($count === 2) {
            return $formatted[0].', & '.$formatted[1];
        }

        $last = array_pop($formatted);

        return implode(', ', $formatted).', & '.$last;
    }

    /**
     * Format authors for Harvard.
     */
    protected static function formatAuthorsHarvard(array $authors): string
    {
        if (empty($authors)) {
            return '';
        }

        $formatted = [];
        foreach ($authors as $author) {
            $formatted[] = self::formatSingleAuthor($author);
        }

        $count = count($formatted);
        if ($count === 1) {
            return $formatted[0];
        }

        if ($count === 2) {
            return $formatted[0].' and '.$formatted[1];
        }

        $last = array_pop($formatted);

        return implode(', ', $formatted).' and '.$last;
    }

    /**
     * Format a single author structure to Family, G.
     */
    protected static function formatSingleAuthor(array|string $author): string
    {
        if (is_string($author)) {
            return trim($author);
        }

        if (! empty($author['literal'])) {
            return trim($author['literal']);
        }

        $family = trim($author['family'] ?? '');
        $given = trim($author['given'] ?? '');

        if (empty($family)) {
            return $given;
        }

        if (empty($given)) {
            return $family;
        }

        // Extract initials
        $givenParts = preg_split('/[\s\.\-]+/', $given, -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';
        foreach ($givenParts as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1)).'.';
        }

        return "{$family}, {$initials}";
    }

    /**
     * Ensure string ends with a period.
     */
    protected static function ensurePeriod(string $str): string
    {
        $trimmed = trim($str);
        if (empty($trimmed)) {
            return '';
        }

        return str_ends_with($trimmed, '.') ? $trimmed : $trimmed.'.';
    }
}
