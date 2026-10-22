<?php

use App\Exceptions\AiConsentRequiredException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\User;
use App\Repositories\HybridSearchRepository;
use App\Services\AiCopilotService;
use App\Services\GeminiEmbeddingService;
use App\Services\RetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create([
        'user_id' => $this->user->id,
        'ai_consent' => false,
    ]);

    $repository = new HybridSearchRepository();
    $embeddingService = new GeminiEmbeddingService();
    $this->retrievalService = new RetrievalService($repository, $embeddingService);
    $this->copilotService = new AiCopilotService($this->retrievalService);
});

test('copilot service strictly enforces consent gate before processing any request', function () {
    expect($this->project->hasAiConsent())->toBeFalse();

    expect(fn () => $this->copilotService->chat(
        project: $this->project,
        message: 'Bagaimana membuat rumusan masalah?',
        mode: AiCopilotService::MODE_EXPLORATORY,
    ))->toThrow(AiConsentRequiredException::class);

    expect(fn () => $this->copilotService->generateProposal(
        project: $this->project,
        selectedText: 'Teks awal skripsi',
        instruction: 'Perbaiki tata bahasa',
        mode: AiCopilotService::MODE_EXPLORATORY,
    ))->toThrow(AiConsentRequiredException::class);
});

test('exploratory mode prompt permits creative ideation with explicit verification warning', function () {
    $this->project->grantAiConsent();

    $capturedPayload = null;
    $this->copilotService->setFakeRunner(function ($type, $payload) use (&$capturedPayload) {
        $capturedPayload = $payload;
        return [
            'reply' => 'Saran eksploratif untuk ide penelitian Anda.',
            'mode' => $payload['mode'],
            'candidate_passages' => [],
            'warning' => 'Exploratory Mode ideas require independent verification before academic use.',
        ];
    });

    $response = $this->copilotService->chat(
        project: $this->project,
        message: 'Ide topik AI untuk pendidikan',
        mode: AiCopilotService::MODE_EXPLORATORY,
    );

    expect($response['mode'])->toBe(AiCopilotService::MODE_EXPLORATORY)
        ->and($response['warning'])->toContain('Exploratory Mode')
        ->and($capturedPayload['system_instruction'])->toContain('Exploratory Mode')
        ->and($capturedPayload['system_instruction'])->toContain('verification');
});

test('evidence-based mode strictly includes candidate passages and refuses unsupported facts in prompt', function () {
    $this->project->grantAiConsent();

    // Create document and chunk
    $document = Document::create([
        'project_id' => $this->project->id,
        'title' => 'Studi AI Pendidikan.pdf',
        'original_filename' => 'studi_ai.pdf',
        'file_path' => 'documents/test.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'status' => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id' => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 4,
        'chunk_index' => 0,
        'content' => 'Tingkat akurasi sistem mencapai 94 persen dalam evaluasi.',
    ]);

    $capturedPayload = null;
    $this->copilotService->setFakeRunner(function ($type, $payload) use (&$capturedPayload) {
        $capturedPayload = $payload;
        return [
            'reply' => 'Berdasarkan dokumen, akurasi mencapai 94 persen.',
            'mode' => $payload['mode'],
            'candidate_passages' => $payload['candidate_passages'],
            'warning' => null,
        ];
    });

    $response = $this->copilotService->chat(
        project: $this->project,
        message: 'Berapa tingkat akurasi sistem?',
        mode: AiCopilotService::MODE_EVIDENCE_BASED,
    );

    expect($response['mode'])->toBe(AiCopilotService::MODE_EVIDENCE_BASED)
        ->and($capturedPayload['system_instruction'])->toContain('Evidence-Based Mode')
        ->and($capturedPayload['system_instruction'])->toContain('REFUSE')
        ->and($capturedPayload['system_instruction'])->toContain('Studi AI Pendidikan.pdf')
        ->and($response['candidate_passages'])->not->toBeEmpty();
});

test('evidence-based mode strictly refuses when research library contains no supporting passages', function () {
    $this->project->grantAiConsent();

    // Project has no documents/chunks
    $response = $this->copilotService->chat(
        project: $this->project,
        message: 'Berapa efektivitas model?',
        mode: AiCopilotService::MODE_EVIDENCE_BASED,
    );

    expect($response['mode'])->toBe(AiCopilotService::MODE_EVIDENCE_BASED)
        ->and($response['candidate_passages'])->toBeEmpty()
        ->and($response['reply'])->toMatch('/tidak memiliki dokumen|lacks supporting evidence|menolak/i');
});

test('proposal generator returns diff payload highlighting additions and deletions', function () {
    $this->project->grantAiConsent();

    $original = 'Penelitian ini menggunakan metode kualitatif deskriptif.';
    $result = $this->copilotService->generateProposal(
        project: $this->project,
        selectedText: $original,
        instruction: 'Tingkatkan bahasa akademis',
        mode: AiCopilotService::MODE_EXPLORATORY,
    );

    expect($result)->toHaveKeys(['original_text', 'proposed_text', 'diff', 'mode', 'explanation'])
        ->and($result['diff'])->toHaveKeys(['original', 'proposed', 'has_changes', 'chunks', 'stats'])
        ->and($result['diff']['has_changes'])->toBeTrue();
});

test('proposal generator in evidence-based mode strictly refuses when research library is empty', function () {
    $this->project->grantAiConsent();

    $original = 'Penelitian ini menggunakan model AI.';
    $result = $this->copilotService->generateProposal(
        project: $this->project,
        selectedText: $original,
        instruction: 'Perbaiki berdasarkan literatur',
        mode: AiCopilotService::MODE_EVIDENCE_BASED,
    );

    expect($result['mode'])->toBe(AiCopilotService::MODE_EVIDENCE_BASED)
        ->and($result['proposed_text'])->toBe($original)
        ->and($result['candidate_passages'])->toBeEmpty()
        ->and($result['diff']['has_changes'])->toBeFalse()
        ->and($result['explanation'])->toMatch('/Penolakan|lacks supporting evidence|tidak memiliki candidate passage/i');
});

test('proposal generator in evidence-based mode grounds suggestion in retrieved candidate passages', function () {
    $this->project->grantAiConsent();

    $document = Document::create([
        'project_id' => $this->project->id,
        'title' => 'Metodologi Penelitian.pdf',
        'original_filename' => 'metodologi.pdf',
        'file_path' => 'documents/metodologi.pdf',
        'file_size' => 1024,
        'mime_type' => 'application/pdf',
        'status' => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id' => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 12,
        'chunk_index' => 0,
        'content' => 'Pendekatan kuantitatif memerlukan uji normalitas Kolmogorov-Smirnov.',
    ]);

    $original = 'Penelitian ini memerlukan pengujian statistik data.';
    $result = $this->copilotService->generateProposal(
        project: $this->project,
        selectedText: $original,
        instruction: 'Hubungkan dengan literatur metodologi',
        mode: AiCopilotService::MODE_EVIDENCE_BASED,
    );

    expect($result['mode'])->toBe(AiCopilotService::MODE_EVIDENCE_BASED)
        ->and($result['candidate_passages'])->not->toBeEmpty()
        ->and($result['diff']['has_changes'])->toBeTrue()
        ->and($result['proposed_text'])->toContain('Metodologi Penelitian.pdf');
});
