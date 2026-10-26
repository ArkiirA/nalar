<?php

namespace App\Services;

use App\Exceptions\AiConsentRequiredException;
use App\Models\Project;
use App\Support\CandidatePassage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EvidenceCheckerService
{
    public const STATUS_SUPPORTED   = 'Supported';
    public const STATUS_CONTRADICTED = 'Contradicted';
    public const STATUS_INSUFFICIENT = 'Insufficient Evidence';
    public const STATUS_UNABLE      = 'Unable to Assess';

    public const VALID_STATUSES = [
        self::STATUS_SUPPORTED,
        self::STATUS_CONTRADICTED,
        self::STATUS_INSUFFICIENT,
        self::STATUS_UNABLE,
    ];

    protected ?string $apiKey;
    protected string $proModel;

    /**
     * @var (callable(array): array)|null
     */
    protected $fakeRunner = null;

    public function __construct(
        protected RetrievalService $retrievalService,
        ?string $apiKey   = null,
        ?string $proModel = null,
    ) {
        $this->apiKey   = $apiKey   ?? config('services.gemini.api_key');
        $this->proModel = $proModel ?? config('services.gemini.pro_model', AiCopilotService::DEFAULT_PRO_MODEL);
    }

    /**
     * Set a fake runner for deterministic testing.
     * Signature: function(array $payload): array{status, explanation, passages}
     */
    public function setFakeRunner(?callable $runner): self
    {
        $this->fakeRunner = $runner;

        return $this;
    }

    /**
     * Assess a student claim against the project's Research Library.
     *
     * @return array{
     *     status: string,
     *     explanation: string,
     *     candidate_passages: array<int, array<string, mixed>>
     * }
     */
    public function assess(Project $project, string $claimText): array
    {
        if (! $project->hasAiConsent()) {
            throw new AiConsentRequiredException;
        }

        $candidatePassages = $this->retrievalService->retrieve(
            projectId: $project->id,
            query: $claimText,
            limit: 5,
        );

        if ($candidatePassages->isEmpty()) {
            return [
                'status'             => self::STATUS_INSUFFICIENT,
                'explanation'        => 'Research Library tidak memiliki candidate passage yang relevan untuk klaim ini. Unggah dokumen pendukung terlebih dahulu.',
                'cited_page_numbers' => [],
                'candidate_passages' => [],
            ];
        }

        $passagesArray = $this->formatPassages($candidatePassages);

        if ($this->fakeRunner !== null) {
            $result = ($this->fakeRunner)([
                'claim_text'         => $claimText,
                'candidate_passages' => $passagesArray,
            ]);

            $status = $this->normalizeStatus($result['status'] ?? self::STATUS_UNABLE);
            $pages = $result['cited_page_numbers'] ?? $candidatePassages->pluck('page_number')->unique()->values()->all();

            return [
                'status'             => $status,
                'explanation'        => $result['explanation'] ?? '',
                'cited_page_numbers' => $pages,
                'candidate_passages' => $result['passages'] ?? $passagesArray,
            ];
        }

        if (empty($this->apiKey)) {
            if (app()->environment('testing')) {
                return $this->deterministicFallback($claimText, $candidatePassages, $passagesArray);
            }
            throw new RuntimeException('Gemini API key is not configured.');
        }

        return $this->dispatchGemini($claimText, $candidatePassages, $passagesArray);
    }

    /**
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @param array<int, array<string, mixed>> $passagesArray
     * @return array<string, mixed>
     */
    protected function dispatchGemini(string $claimText, Collection $candidatePassages, array $passagesArray): array
    {
        $passagesText = '';
        foreach ($candidatePassages as $idx => $passage) {
            $passagesText .= sprintf(
                "[%d] [Sumber: %s, Halaman %d]: %s\n",
                $idx + 1,
                $passage->document_title,
                $passage->page_number,
                $passage->content,
            );
        }

        $prompt = "Kamu adalah Citation Evidence Checker untuk penulisan skripsi akademik Indonesia.\n"
            . "Tugasmu: evaluasi apakah KLAIM mahasiswa didukung, dibantah, tidak cukup bukti, atau tidak dapat dinilai berdasarkan CANDIDATE PASSAGES dari Research Library.\n\n"
            . "KLAIM:\n{$claimText}\n\n"
            . "CANDIDATE PASSAGES:\n{$passagesText}\n\n"
            . "Aturan ketat:\n"
            . "1. Gunakan HANYA candidate passages di atas — jangan tambahkan pengetahuan eksternal.\n"
            . "2. Jika passages tidak cukup, nyatakan 'Insufficient Evidence' atau 'Unable to Assess'.\n"
            . "3. Jawab dengan JSON valid berisi:\n"
            . "   - \"status\": salah satu dari \"Supported\", \"Contradicted\", \"Insufficient Evidence\", \"Unable to Assess\"\n"
            . "   - \"explanation\": penjelasan singkat dalam Bahasa Indonesia (max 3 kalimat)\n"
            . "   - \"cited_page_numbers\": array nomor halaman integer dari candidate passages yang menjadi rujukan (misal [5, 12])\n";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->proModel}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(45)->post($url, [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.1,
            ],
        ]);

        if (! $response->successful()) {
            $msg = $response->json('error.message') ?? "Gemini API error (HTTP {$response->status()})";
            throw new RuntimeException($msg);
        }

        $raw  = $response->json('candidates.0.content.parts.0.text', '{}');
        $data = json_decode($raw, true) ?: [];

        $citedPages = isset($data['cited_page_numbers']) && is_array($data['cited_page_numbers'])
            ? array_values(array_map('intval', $data['cited_page_numbers']))
            : $candidatePassages->pluck('page_number')->unique()->values()->all();

        return [
            'status'             => $this->normalizeStatus($data['status'] ?? self::STATUS_UNABLE),
            'explanation'        => $data['explanation'] ?? 'Tidak dapat menentukan status klaim.',
            'cited_page_numbers' => $citedPages,
            'candidate_passages' => $passagesArray,
        ];
    }

    /**
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @param array<int, array<string, mixed>> $passagesArray
     * @return array<string, mixed>
     */
    protected function deterministicFallback(string $claimText, Collection $candidatePassages, array $passagesArray): array
    {
        $first = $candidatePassages->first();
        $pages = $candidatePassages->pluck('page_number')->unique()->values()->all();

        return [
            'status'             => self::STATUS_SUPPORTED,
            'explanation'        => sprintf(
                'Berdasarkan literatur "%s" (hlm. %d), ditemukan bukti yang mendukung klaim.',
                $first->document_title,
                $first->page_number,
            ),
            'cited_page_numbers' => $pages,
            'candidate_passages' => $passagesArray,
        ];
    }

    protected function normalizeStatus(string $status): string
    {
        return in_array($status, self::VALID_STATUSES, true) ? $status : self::STATUS_UNABLE;
    }

    /**
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @return array<int, array<string, mixed>>
     */
    protected function formatPassages(Collection $candidatePassages): array
    {
        return $candidatePassages->map(fn (CandidatePassage $p) => $p->toArray())->values()->all();
    }
}
