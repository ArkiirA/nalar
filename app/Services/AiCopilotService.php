<?php

namespace App\Services;

use App\Exceptions\AiConsentRequiredException;
use App\Models\Project;
use App\Support\CandidatePassage;
use App\Support\DiffFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AiCopilotService
{
    public const MODE_EXPLORATORY = 'exploratory';
    public const MODE_EVIDENCE_BASED = 'evidence_based';

    public const DEFAULT_CHAT_MODEL = 'gemini-1.5-flash';
    public const DEFAULT_PRO_MODEL = 'gemini-1.5-pro';

    protected ?string $apiKey;
    protected string $model;
    protected string $proModel;

    /**
     * @var (callable(string, array): array)|null
     */
    protected $fakeRunner = null;

    public function __construct(
        protected RetrievalService $retrievalService,
        ?string $apiKey = null,
        ?string $model = null,
        ?string $proModel = null,
    ) {
        $this->apiKey = $apiKey ?? config('services.gemini.api_key');
        $this->model = $model ?? config('services.gemini.chat_model', self::DEFAULT_CHAT_MODEL);
        $this->proModel = $proModel ?? config('services.gemini.pro_model', self::DEFAULT_PRO_MODEL);
    }

    /**
     * Set a custom fake runner callback for deterministic testing.
     * Signature: function(string $type, array $payload): array
     */
    public function setFakeRunner(?callable $runner): self
    {
        $this->fakeRunner = $runner;

        return $this;
    }

    /**
     * Chat with the Gemini-powered copilot in Exploratory or Evidence-Based mode.
     *
     * @param array<int, array{role: string, content: string}> $history
     * @return array{
     *     reply: string,
     *     mode: string,
     *     candidate_passages: array<int, array<string, mixed>>,
     *     warning: string|null
     * }
     */
    public function chat(
        Project $project,
        string $message,
        string $mode = self::MODE_EVIDENCE_BASED,
        array $history = [],
        ?string $contextText = null,
    ): array {
        if (! $project->hasAiConsent()) {
            throw new AiConsentRequiredException('External AI processing consent has not been granted for this project.');
        }

        $candidatePassages = collect();

        if ($mode === self::MODE_EVIDENCE_BASED) {
            $candidatePassages = $this->retrievalService->retrieve(
                projectId: $project->id,
                query: $message,
                limit: 5,
            );
        }

        $systemInstruction = $this->buildSystemInstruction($mode, $candidatePassages, $contextText);

        if ($this->fakeRunner !== null) {
            $passagesArray = $this->formatPassages($candidatePassages);

            return ($this->fakeRunner)('chat', [
                'project' => $project,
                'message' => $message,
                'mode' => $mode,
                'history' => $history,
                'system_instruction' => $systemInstruction,
                'candidate_passages' => $passagesArray,
            ]);
        }

        if (empty($this->apiKey)) {
            if (app()->environment('testing')) {
                return $this->generateDeterministicFakeChat(
                    message: $message,
                    mode: $mode,
                    candidatePassages: $candidatePassages,
                );
            }
            throw new RuntimeException('Gemini API key is not configured.');
        }

        return $this->dispatchGeminiChat(
            message: $message,
            mode: $mode,
            systemInstruction: $systemInstruction,
            history: $history,
            candidatePassages: $candidatePassages,
        );
    }

    /**
     * Generate an AI proposal for selected text with side-by-side diff.
     *
     * @return array{
     *     original_text: string,
     *     proposed_text: string,
     *     mode: string,
     *     diff: array<string, mixed>,
     *     explanation: string,
     *     candidate_passages: array<int, array<string, mixed>>
     * }
     */
    public function generateProposal(
        Project $project,
        string $selectedText,
        string $instruction,
        string $mode = self::MODE_EVIDENCE_BASED,
    ): array {
        if (! $project->hasAiConsent()) {
            throw new AiConsentRequiredException('External AI processing consent has not been granted for this project.');
        }

        $candidatePassages = collect();

        if ($mode === self::MODE_EVIDENCE_BASED) {
            $searchQuery = trim($selectedText . ' ' . $instruction);
            $candidatePassages = $this->retrievalService->retrieve(
                projectId: $project->id,
                query: $searchQuery,
                limit: 3,
            );

            if ($candidatePassages->isEmpty()) {
                return [
                    'original_text' => $selectedText,
                    'proposed_text' => $selectedText,
                    'mode' => $mode,
                    'diff' => DiffFormatter::format($selectedText, $selectedText),
                    'explanation' => 'Penolakan: Research Library tidak memiliki candidate passage pendukung untuk melakukan revisi berbasis bukti.',
                    'candidate_passages' => [],
                ];
            }
        }

        $passagesArray = $this->formatPassages($candidatePassages);

        if ($this->fakeRunner !== null) {
            $fakeRes = ($this->fakeRunner)('proposal', [
                'project' => $project,
                'selected_text' => $selectedText,
                'instruction' => $instruction,
                'mode' => $mode,
                'candidate_passages' => $passagesArray,
            ]);

            $proposed = $fakeRes['proposed_text'] ?? $selectedText;
            $diff = DiffFormatter::format($selectedText, $proposed);

            return [
                'original_text' => $selectedText,
                'proposed_text' => $proposed,
                'mode' => $mode,
                'diff' => $diff,
                'explanation' => $fakeRes['explanation'] ?? 'Usulan revisi teks.',
                'candidate_passages' => $passagesArray,
            ];
        }

        if (empty($this->apiKey)) {
            if (app()->environment('testing')) {
                return $this->generateDeterministicFakeProposal(
                    selectedText: $selectedText,
                    instruction: $instruction,
                    mode: $mode,
                    candidatePassages: $candidatePassages,
                    passagesArray: $passagesArray,
                );
            }
            throw new RuntimeException('Gemini API key is not configured.');
        }

        return $this->dispatchGeminiProposal(
            selectedText: $selectedText,
            instruction: $instruction,
            mode: $mode,
            candidatePassages: $candidatePassages,
            passagesArray: $passagesArray,
        );
    }

    /**
     * Build system prompt instructions based on mode.
     *
     * @param Collection<int, CandidatePassage> $candidatePassages
     */
    protected function buildSystemInstruction(string $mode, Collection $candidatePassages, ?string $contextText = null): string
    {
        if ($mode === self::MODE_EXPLORATORY) {
            $instruction = "You are Nalar AI Copilot in Exploratory Mode for Indonesian undergraduate thesis writing.\n".
                "Role: Assist students in creative ideation, brainstorming research topics, questions, outlines, and theoretical perspectives.\n".
                "Rules:\n".
                "1. Creative ideation is permitted. You may suggest perspectives not yet in the student's library.\n".
                "2. Clearly indicate that ideas and suggestions require independent student verification before academic use.\n".
                "3. Do not fabricate citations or claim that ideas are verified from the library.\n".
                "4. Language: Respond in clear, academic Indonesian (or English if prompted in English).";

            if (! empty($contextText)) {
                $instruction .= "\n\nCurrent Chapter Context:\n".$contextText;
            }

            return $instruction;
        }

        // Evidence-Based Mode
        $passagesSection = '';
        if ($candidatePassages->isEmpty()) {
            $passagesSection = "[NO CANDIDATE PASSAGES FOUND IN RESEARCH LIBRARY]\n".
                "Strict Refusal Rule: The research library contains no matching evidence for this topic. You MUST strictly REFUSE to invent, speculate, or state factual claims. Explicitly state that the research library lacks supporting evidence.";
        } else {
            $passagesSection = "Candidate Passages from Student Research Library:\n";
            foreach ($candidatePassages as $idx => $passage) {
                $passagesSection .= sprintf(
                    "[%d] [Source: %s, Page %d]: %s\n",
                    $idx + 1,
                    $passage->document_title,
                    $passage->page_number,
                    $passage->content
                );
            }
        }

        $instruction = "You are Nalar AI Copilot in Evidence-Based Mode for Indonesian undergraduate thesis writing.\n".
            "Role: Closed-library grounded academic drafting assistant.\n".
            "Rules:\n".
            "1. You must ONLY state facts, findings, and arguments directly supported by the provided candidate passages.\n".
            "2. You must strictly REFUSE to state facts, extrapolate findings, or invent claims not supported by the retrieved candidate passages.\n".
            "3. If candidate passages do not contain sufficient evidence, state explicitly that the research library lacks supporting evidence.\n".
            "4. Always cite the source title and page number for every factual claim.\n\n".
            $passagesSection;

        if (! empty($contextText)) {
            $instruction .= "\n\nCurrent Chapter Context:\n".$contextText;
        }

        return $instruction;
    }

    /**
     * Dispatch request to Gemini API for copilot chat.
     *
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @return array{
     *     reply: string,
     *     mode: string,
     *     candidate_passages: array<int, array<string, mixed>>,
     *     warning: string|null
     * }
     */
    protected function dispatchGeminiChat(
        string $message,
        string $mode,
        string $systemInstruction,
        array $history,
        Collection $candidatePassages,
    ): array {
        $contents = [];
        foreach ($history as $item) {
            $role = ($item['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => (string) ($item['content'] ?? '')],
                ],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $message],
            ],
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(45)->post($url, [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'generationConfig' => [
                'temperature' => $mode === self::MODE_EXPLORATORY ? 0.7 : 0.2,
            ],
        ]);

        if (! $response->successful()) {
            $errorMsg = $response->json('error.message') ?? "Gemini API error (HTTP {$response->status()})";
            throw new RuntimeException($errorMsg);
        }

        $reply = $response->json('candidates.0.content.parts.0.text', '');

        $passagesArray = $this->formatPassages($candidatePassages);

        return [
            'reply' => $reply,
            'mode' => $mode,
            'candidate_passages' => $passagesArray,
            'warning' => $mode === self::MODE_EXPLORATORY
                ? 'Exploratory Mode ideas require independent verification before academic use.'
                : null,
        ];
    }

    /**
     * Dispatch request to Gemini API for proposal generation.
     *
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @param array<int, array<string, mixed>> $passagesArray
     * @return array<string, mixed>
     */
    protected function dispatchGeminiProposal(
        string $selectedText,
        string $instruction,
        string $mode,
        Collection $candidatePassages,
        array $passagesArray,
    ): array {
        $prompt = "You are an academic thesis writing assistant.\n".
            "Task: Rewrite the provided original text following the student's instruction.\n".
            ($mode === self::MODE_EVIDENCE_BASED
                ? "Strict Rule: Ground the revision strictly in the candidate passages. Refuse to invent unsupported facts.\n"
                : "Rule: Suggest creative improvements while keeping academic thesis standards.\n").
            "Original text:\n{$selectedText}\n\n".
            "Instruction:\n{$instruction}\n\n".
            "Output JSON format strictly with keys 'proposed_text' (string) and 'explanation' (string in Indonesian).";

        $systemInstruction = $this->buildSystemInstruction($mode, $candidatePassages);

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->proModel}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(45)->post($url, [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]],
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.2,
            ],
        ]);

        if (! $response->successful()) {
            $errorMsg = $response->json('error.message') ?? "Gemini API error (HTTP {$response->status()})";
            throw new RuntimeException($errorMsg);
        }

        $rawJson = $response->json('candidates.0.content.parts.0.text', '{}');
        $data = json_decode($rawJson, true) ?: [];

        $proposedText = $data['proposed_text'] ?? $selectedText;
        $explanation = $data['explanation'] ?? 'Revisi teks sesuai instruksi.';

        return [
            'original_text' => $selectedText,
            'proposed_text' => $proposedText,
            'mode' => $mode,
            'diff' => DiffFormatter::format($selectedText, $proposedText),
            'explanation' => $explanation,
            'candidate_passages' => $passagesArray,
        ];
    }

    /**
     * Deterministic chat fallback for testing without external API calls.
     *
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @return array{
     *     reply: string,
     *     mode: string,
     *     candidate_passages: array<int, array<string, mixed>>,
     *     warning: string|null
     * }
     */
    protected function generateDeterministicFakeChat(string $message, string $mode, Collection $candidatePassages): array
    {
        $passagesArray = $this->formatPassages($candidatePassages);

        if ($mode === self::MODE_EXPLORATORY) {
            return [
                'reply' => sprintf(
                    "[Gagasan Eksploratif] Untuk pertanyaan '%s', Anda dapat mengeksplorasi hubungan antar variabel atau kerangka konseptual baru. Perhatikan bahwa gagasan ini membutuhkan verifikasi mandiri sebelum digunakan dalam skripsi.",
                    $message
                ),
                'mode' => self::MODE_EXPLORATORY,
                'candidate_passages' => [],
                'warning' => 'Exploratory Mode ideas require independent verification before academic use.',
            ];
        }

        // Evidence-Based Mode
        if ($candidatePassages->isEmpty()) {
            return [
                'reply' => 'Research Library tidak memiliki dokumen atau bukti yang memadai untuk menjawab pertanyaan ini. Dalam Evidence-Based Mode, saya menolak memberikan klaim faktual tanpa dukungan passage dari Research Library.',
                'mode' => self::MODE_EVIDENCE_BASED,
                'candidate_passages' => [],
                'warning' => null,
            ];
        }

        $first = $candidatePassages->first();

        return [
            'reply' => sprintf(
                "Berdasarkan literatur '%s' (hlm. %d), ditemukan bukti: %s",
                $first->document_title,
                $first->page_number,
                $first->content
            ),
            'mode' => self::MODE_EVIDENCE_BASED,
            'candidate_passages' => $passagesArray,
            'warning' => null,
        ];
    }

    /**
     * Deterministic proposal fallback for testing without external API calls.
     *
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @param array<int, array<string, mixed>> $passagesArray
     * @return array<string, mixed>
     */
    protected function generateDeterministicFakeProposal(
        string $selectedText,
        string $instruction,
        string $mode,
        Collection $candidatePassages,
        array $passagesArray,
    ): array {
        if ($mode === self::MODE_EVIDENCE_BASED && $candidatePassages->isEmpty()) {
            return [
                'original_text' => $selectedText,
                'proposed_text' => $selectedText,
                'mode' => $mode,
                'diff' => DiffFormatter::format($selectedText, $selectedText),
                'explanation' => 'Penolakan: Research Library tidak memiliki passage pendukung untuk melakukan revisi berbasis bukti.',
                'candidate_passages' => [],
            ];
        }

        if ($mode === self::MODE_EXPLORATORY) {
            $proposed = rtrim($selectedText, '.') . ' secara komprehensif dan mendalam.';
            $explanation = 'Menyempurnakan kalimat untuk memperkuat kedalaman eksplorasi akademis.';
        } else {
            $source = $candidatePassages->first();
            $proposed = rtrim($selectedText, '.') . sprintf(' sebagaimana dibuktikan oleh %s (hlm. %d).', $source->document_title, $source->page_number);
            $explanation = 'Menghubungkan kalimat secara langsung dengan bukti dari Research Library.';
        }

        return [
            'original_text' => $selectedText,
            'proposed_text' => $proposed,
            'mode' => $mode,
            'diff' => DiffFormatter::format($selectedText, $proposed),
            'explanation' => $explanation,
            'candidate_passages' => $passagesArray,
        ];
    }

    /**
     * Format a collection of CandidatePassage objects into an array.
     *
     * @param Collection<int, CandidatePassage> $candidatePassages
     * @return array<int, array<string, mixed>>
     */
    protected function formatPassages(Collection $candidatePassages): array
    {
        return $candidatePassages->map(fn (CandidatePassage $p) => [
            'id' => $p->id,
            'document_id' => $p->document_id,
            'document_title' => $p->document_title,
            'page_number' => $p->page_number,
            'content' => $p->content,
            'score' => $p->score,
        ])->values()->all();
    }
}
