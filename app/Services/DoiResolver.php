<?php

namespace App\Services;

use App\Models\Reference;
use Illuminate\Support\Facades\Http;
use Throwable;

class DoiResolver
{
    /**
     * Normalize raw DOI input by stripping URLs and prefixes.
     */
    public function normalizeDoi(string $rawDoi): string
    {
        $doi = trim($rawDoi);
        $doi = preg_replace('#^https?://(dx\.)?doi\.org/#i', '', $doi);
        $doi = preg_replace('#^doi:#i', '', $doi);

        return trim($doi);
    }

    /**
     * Resolve bibliographic metadata for a given DOI using content negotiation.
     *
     * @return array{success: bool, data?: array<string, mixed>, error?: string}
     */
    public function resolve(string $rawDoi): array
    {
        $doi = $this->normalizeDoi($rawDoi);

        if (empty($doi)) {
            return [
                'success' => false,
                'error' => 'DOI tidak boleh kosong.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.citationstyles.csl+json',
                    'User-Agent' => 'NalarAcademicClient/1.0 (mailto:support@nalar.app)',
                ])
                ->get("https://doi.org/{$doi}");

            if (! $response->successful()) {
                // Fallback to CrossRef API
                $crossref = Http::timeout(10)
                    ->get("https://api.crossref.org/works/{$doi}");

                if ($crossref->successful() && isset($crossref->json()['message'])) {
                    $item = $crossref->json()['message'];
                    $attributes = $this->parseCrossrefItem($item, $doi);

                    return [
                        'success' => true,
                        'data' => $attributes,
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'DOI tidak ditemukan atau server registri DOI tidak merespons (Status: '.$response->status().').',
                ];
            }

            $csl = $response->json();
            if (! is_array($csl)) {
                return [
                    'success' => false,
                    'error' => 'Format respons dari penyedia DOI tidak valid.',
                ];
            }

            $attributes = Reference::fromCslJson($csl);
            $attributes['doi'] = $doi;

            return [
                'success' => true,
                'data' => $attributes,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'error' => 'Gagal menghubungi registri DOI: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Helper to map CrossRef work format when content negotiation returns alternative.
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function parseCrossrefItem(array $item, string $doi): array
    {
        $authors = [];
        if (! empty($item['author'])) {
            foreach ($item['author'] as $author) {
                $authors[] = [
                    'family' => $author['family'] ?? '',
                    'given' => $author['given'] ?? '',
                ];
            }
        }

        $year = null;
        if (! empty($item['published-print']['date-parts'][0][0])) {
            $year = (int) $item['published-print']['date-parts'][0][0];
        } elseif (! empty($item['created']['date-parts'][0][0])) {
            $year = (int) $item['created']['date-parts'][0][0];
        }

        $title = ! empty($item['title'][0]) ? $item['title'][0] : '';
        $journal = ! empty($item['container-title'][0]) ? $item['container-title'][0] : null;

        return [
            'type' => 'article-journal',
            'title' => $title,
            'authors' => $authors,
            'year' => $year,
            'publication_title' => $journal,
            'volume' => isset($item['volume']) ? (string) $item['volume'] : null,
            'issue' => isset($item['issue']) ? (string) $item['issue'] : null,
            'pages' => $item['page'] ?? null,
            'publisher' => $item['publisher'] ?? null,
            'doi' => $doi,
            'csl_data' => $item,
        ];
    }
}
