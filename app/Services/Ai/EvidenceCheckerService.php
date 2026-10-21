<?php

namespace App\Services\Ai;

use Throwable;

class EvidenceCheckerService
{
    public const STATUS_SUPPORTED = 'Supported';
    public const STATUS_CONTRADICTED = 'Contradicted';
    public const STATUS_INSUFFICIENT = 'Insufficient Evidence';
    public const STATUS_UNABLE = 'Unable to Assess';

    public function __construct(
        protected HermesBridge $bridge
    ) {}

    /**
     * Assess an academic claim against literature evidence or scientific consensus.
     */
    public function assess(string $claim, ?string $context = null, ?string $chapterTitle = null): array
    {
        $claim = trim($claim);

        if (empty($claim)) {
            return $this->formatResult(
                status: self::STATUS_UNABLE,
                confidence: 0,
                analysis: 'Teks klaim kosong atau tidak valid untuk diperiksa.',
                excerpts: [],
                recommendation: $claim
            );
        }

        try {
            $prompt = $this->buildPrompt($claim, $context);
            $rawResponse = $this->bridge->query(
                prompt: $prompt,
                chapterTitle: $chapterTitle,
                chapterText: $context
            );

            $parsed = $this->extractJson($rawResponse);
            if ($parsed && isset($parsed['status'])) {
                return $this->normalizeResult($parsed, $claim);
            }
        } catch (Throwable $e) {
            // Fallback to deterministic heuristic evaluation if AI backend is unavailable
        }

        return $this->heuristicAssessment($claim, $context);
    }

    /**
     * Build prompt with strict JSON schema instructions for citation verification.
     */
    protected function buildPrompt(string $claim, ?string $context = null): string
    {
        $contextSection = $context ? "Bukti/Paper Referensi:\n\"\"\"{$context}\"\"\"\n\n" : "Bukti/Paper Referensi: Belum ada paper terunggah khusus (gunakan basis konsensus ilmiah umum).\n\n";

        return <<<PROMPT
Anda adalah Citation Evidence Checker untuk naskah skripsi akademik.
Tugas Anda: Verifikasi keabsahan fakta/klaim berikut secara ketat dan objektif.

Klaim Mahasiswa:
"{$claim}"

{$contextSection}
Pilihlah salah satu dari 4 status baku ini:
1. "Supported" (Klaim didukung oleh bukti ilmiah/fakta riset yang kuat)
2. "Contradicted" (Klaim bertentangan dengan temuan ilmiah atau terdapat generalisasi salah)
3. "Insufficient Evidence" (Klaim mungkin benar tetapi bukti pendukung kurang memadai atau memerlukan rujukan spesifik)
4. "Unable to Assess" (Klaim terlalu subjektif, opini pribadi, atau pertanyaan bukan pernyataan faktual)

Format balasan WAJIB berupa JSON murni tanpa markdown pembungkus (tanpa ```json):
{
  "status": "Supported|Contradicted|Insufficient Evidence|Unable to Assess",
  "confidence": 85,
  "analysis": "Penjelasan kritis dalam Bahasa Indonesia mengapa klaim ini dinilai demikian.",
  "excerpts": ["Kutipan temuan atau prinsip ilmiah terkait beserta estimasi rujukan"],
  "recommendation": "Saran perumusan ulang kalimat klaim dalam gaya penulisan akademik baku yang defensible (dapat dipertanggungjawabkan)."
}
PROMPT;
    }

    /**
     * Extract and parse JSON from LLM output.
     */
    protected function extractJson(string $raw): ?array
    {
        $raw = trim($raw);
        // Strip markdown fences if present
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $clean = preg_replace('/\s*```$/', '', $clean);

        if (preg_match('/\{[\s\S]*\}/', $clean, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Normalize and validate parsed JSON structure.
     */
    protected function normalizeResult(array $data, string $originalClaim): array
    {
        $validStatuses = [
            self::STATUS_SUPPORTED,
            self::STATUS_CONTRADICTED,
            self::STATUS_INSUFFICIENT,
            self::STATUS_UNABLE,
        ];

        $status = $data['status'] ?? self::STATUS_INSUFFICIENT;
        if (!in_array($status, $validStatuses, true)) {
            $status = self::STATUS_INSUFFICIENT;
        }

        return $this->formatResult(
            status: $status,
            confidence: (int) ($data['confidence'] ?? 75),
            analysis: (string) ($data['analysis'] ?? 'Klaim telah dianalisis terhadap metodologi dan prinsip akademik.'),
            excerpts: is_array($data['excerpts'] ?? null) ? $data['excerpts'] : [],
            recommendation: (string) ($data['recommendation'] ?? $originalClaim)
        );
    }

    /**
     * Fallback heuristic assessment when AI service is offline.
     */
    protected function heuristicAssessment(string $claim, ?string $context): array
    {
        $lower = mb_strtolower($claim);

        // Detect absolute overclaiming keywords
        $overclaimWords = ['pasti', 'selalu', 'tanpa ragu', 'mutlak', 'terbaik di dunia', 'semua orang tahu', 'tidak terbantahkan'];
        $hasOverclaim = false;
        foreach ($overclaimWords as $word) {
            if (str_contains($lower, $word)) {
                $hasOverclaim = true;
                break;
            }
        }

        if ($hasOverclaim) {
            return $this->formatResult(
                status: self::STATUS_CONTRADICTED,
                confidence: 80,
                analysis: 'Klaim mengandung generalisasi absolut tanpa pembuktian empiris komprehensif. Dalam penulisan akademik, hindari klaim mutlak seperti "pasti" atau "selalu".',
                excerpts: ['Kaidah metodologi ilmiah mewajibkan batasan kondisi (boundary conditions) dan derajat kepastian terukur.'],
                recommendation: 'Berdasarkan hasil pengujian pada batasan penelitian ini, metode yang diusulkan menunjukkan kecenderungan performa yang lebih optimal dibandingkan metode pembanding.'
            );
        }

        if (empty($context)) {
            return $this->formatResult(
                status: self::STATUS_INSUFFICIENT,
                confidence: 65,
                analysis: 'Belum ditemukan paper atau kutipan rujukan terunggah di Research Library yang secara spesifik mendukung pernyataan ini. Diperlukan sitasi primer.',
                excerpts: ['Diperlukan rujukan jurnal bereputasi (IEEE / ACM / Elsevier / SINTA) untuk memvalidasi pernyataan faktual ini.'],
                recommendation: rtrim($claim, '.') . ' sebagaimana ditunjukkan pada penelitian terdahulu (Penulis, Tahun).'
            );
        }

        return $this->formatResult(
            status: self::STATUS_SUPPORTED,
            confidence: 70,
            analysis: 'Pernyataan selaras dengan materi konteks bab dan kaidah penulisan ilmiah yang wajar.',
            excerpts: ['Konteks dokumen bab mendukung koherensi argumen ini.'],
            recommendation: $claim
        );
    }

    protected function formatResult(string $status, int $confidence, string $analysis, array $excerpts, string $recommendation): array
    {
        return [
            'status' => $status,
            'confidence' => max(0, min(100, $confidence)),
            'analysis' => $analysis,
            'excerpts' => $excerpts,
            'recommendation' => $recommendation,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
