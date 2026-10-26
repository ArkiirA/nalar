<?php

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\EvidenceAssessment;
use App\Models\Claim;
use App\Models\Project;
use App\Models\User;
use App\Services\EvidenceCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->project = Project::factory()->create([
        'user_id'       => $this->user->id,
        'ai_consent'    => true,
        'ai_consent_at' => now(),
    ]);
});

// ---------------------------------------------------------------------------
// Authentication & Authorization
// ---------------------------------------------------------------------------

test('unauthenticated users cannot access evidence checker endpoint', function () {
    $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Penelitian ini menunjukkan hasil positif.',
    ])->assertUnauthorized();
});

test('unauthorized user cannot check evidence in another users project', function () {
    $this->actingAs($this->otherUser);

    $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Penelitian ini menunjukkan hasil positif.',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// Consent Gate
// ---------------------------------------------------------------------------

test('evidence checker is blocked when ai consent is not granted', function () {
    $project = Project::factory()->create([
        'user_id'    => $this->user->id,
        'ai_consent' => false,
    ]);

    $this->actingAs($this->user);

    $this->postJson(route('projects.evidence.check', $project), [
        'claim_text' => 'Metode ini meningkatkan akurasi.',
    ])->assertStatus(403)
        ->assertJson([
            'error' => 'ai_consent_required',
        ]);
});

// ---------------------------------------------------------------------------
// Input Validation
// ---------------------------------------------------------------------------

test('evidence checker validates claim_text is required', function () {
    $this->actingAs($this->user);

    $this->postJson(route('projects.evidence.check', $this->project), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['claim_text']);
});

// ---------------------------------------------------------------------------
// Assessment Status Categorization
// ---------------------------------------------------------------------------

test('assessment returns Supported status when passages clearly support the claim', function () {
    $this->actingAs($this->user);

    $document = Document::create([
        'project_id'        => $this->project->id,
        'title'             => 'Algoritma Random Forest.pdf',
        'original_filename' => 'rf.pdf',
        'file_path'         => 'documents/rf.pdf',
        'file_size'         => 1024,
        'mime_type'         => 'application/pdf',
        'status'            => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id'  => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 5,
        'chunk_index' => 0,
        'content'     => 'Random Forest menghasilkan akurasi 95% pada dataset uji.',
    ]);

    $service = app(EvidenceCheckerService::class);
    $service->setFakeRunner(fn ($payload) => [
        'status'      => 'Supported',
        'explanation' => 'Klaim didukung oleh bukti dari Random Forest.',
        'passages'    => $payload['candidate_passages'],
    ]);
    app()->instance(EvidenceCheckerService::class, $service);

    $response = $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Random Forest menghasilkan akurasi 95%.',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'claim_id',
            'status',
            'explanation',
            'cited_page_numbers',
            'candidate_passages',
        ])
        ->assertJson([
            'status' => 'Supported',
        ]);

    expect($response->json('cited_page_numbers'))->toContain(5);
});

test('assessment returns Contradicted status when passages contradict the claim', function () {
    $this->actingAs($this->user);

    $document = Document::create([
        'project_id'        => $this->project->id,
        'title'             => 'Evaluasi Metode.pdf',
        'original_filename' => 'eval.pdf',
        'file_path'         => 'documents/eval.pdf',
        'file_size'         => 512,
        'mime_type'         => 'application/pdf',
        'status'            => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id'  => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 12,
        'chunk_index' => 0,
        'content'     => 'Model ini hanya mencapai akurasi 61%, jauh di bawah baseline.',
    ]);

    $service = app(EvidenceCheckerService::class);
    $service->setFakeRunner(fn ($payload) => [
        'status'      => 'Contradicted',
        'explanation' => 'Bukti menunjukkan akurasi rendah, bertentangan dengan klaim.',
        'passages'    => $payload['candidate_passages'],
    ]);
    app()->instance(EvidenceCheckerService::class, $service);

    $response = $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Model ini mencapai akurasi sangat tinggi.',
    ]);

    $response->assertOk()->assertJson(['status' => 'Contradicted']);
});

test('assessment returns Insufficient Evidence when library has no matching passages', function () {
    $this->actingAs($this->user);

    // No chunks uploaded
    $service = app(EvidenceCheckerService::class);
    // No fake runner — empty library path is handled inside the service
    app()->instance(EvidenceCheckerService::class, $service);

    $response = $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Klaim yang tidak ada dukungan literaturnya sama sekali.',
    ]);

    $response->assertOk()->assertJson(['status' => 'Insufficient Evidence']);
});

test('assessment returns Unable to Assess when fake runner signals ambiguity', function () {
    $this->actingAs($this->user);

    $document = Document::create([
        'project_id'        => $this->project->id,
        'title'             => 'Dokumen Ambigu.pdf',
        'original_filename' => 'ambigu.pdf',
        'file_path'         => 'documents/ambigu.pdf',
        'file_size'         => 512,
        'mime_type'         => 'application/pdf',
        'status'            => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id'  => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 3,
        'chunk_index' => 0,
        'content'     => 'Metode ini selalu bervariasi efektivitasnya tergantung konteks dan konfigurasi.',
    ]);

    $service = app(EvidenceCheckerService::class);
    $service->setFakeRunner(fn ($payload) => [
        'status'      => 'Unable to Assess',
        'explanation' => 'Passage tidak cukup spesifik untuk menentukan dukungan atau kontradiksi.',
        'passages'    => $payload['candidate_passages'],
    ]);
    app()->instance(EvidenceCheckerService::class, $service);

    $response = $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Metode ini selalu optimal.',
    ]);

    $response->assertOk()->assertJson(['status' => 'Unable to Assess']);
});

// ---------------------------------------------------------------------------
// Excerpt Page Attribution
// ---------------------------------------------------------------------------

test('assessment includes source title and page number in candidate passages', function () {
    $this->actingAs($this->user);

    $document = Document::create([
        'project_id'        => $this->project->id,
        'title'             => 'Jurnal Akurasi Model.pdf',
        'original_filename' => 'akurasi.pdf',
        'file_path'         => 'documents/akurasi.pdf',
        'file_size'         => 2048,
        'mime_type'         => 'application/pdf',
        'status'            => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id'  => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 17,
        'chunk_index' => 0,
        'content'     => 'Hasil eksperimen menunjukkan peningkatan 23% pada metrik F1-score.',
    ]);

    $service = app(EvidenceCheckerService::class);
    $service->setFakeRunner(fn ($payload) => [
        'status'      => 'Supported',
        'explanation' => 'Bukti mendukung klaim peningkatan.',
        'passages'    => $payload['candidate_passages'],
    ]);
    app()->instance(EvidenceCheckerService::class, $service);

    $response = $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Metode ini meningkatkan F1-score sebesar 23%.',
    ]);

    $response->assertOk();
    $passages = $response->json('candidate_passages');
    expect($passages)->not->toBeEmpty()
        ->and($passages[0]['document_title'])->toBe('Jurnal Akurasi Model.pdf')
        ->and($passages[0]['page_number'])->toBe(17);
});

// ---------------------------------------------------------------------------
// Claim & Assessment Persistence
// ---------------------------------------------------------------------------

test('check endpoint persists a Claim record in the database', function () {
    $this->actingAs($this->user);

    $service = app(EvidenceCheckerService::class);
    $service->setFakeRunner(fn ($payload) => [
        'status'      => 'Insufficient Evidence',
        'explanation' => 'Tidak ada dokumen pendukung.',
        'passages'    => [],
    ]);
    app()->instance(EvidenceCheckerService::class, $service);

    $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Klaim yang akan disimpan ke database.',
    ])->assertOk();

    $this->assertDatabaseHas('claims', [
        'project_id' => $this->project->id,
        'claim_text' => 'Klaim yang akan disimpan ke database.',
    ]);
});

test('check endpoint persists an EvidenceAssessment linked to the Claim', function () {
    $this->actingAs($this->user);

    $document = Document::create([
        'project_id'        => $this->project->id,
        'title'             => 'Dokumen Uji Persistensi.pdf',
        'original_filename' => 'uji.pdf',
        'file_path'         => 'documents/uji.pdf',
        'file_size'         => 512,
        'mime_type'         => 'application/pdf',
        'status'            => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id'  => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'chunk_index' => 0,
        'content'     => 'Klaim untuk uji persistensi assessment telah terbukti secara empiris.',
    ]);

    $service = app(EvidenceCheckerService::class);
    $service->setFakeRunner(fn ($payload) => [
        'status'      => 'Supported',
        'explanation' => 'Didukung sepenuhnya.',
        'passages'    => $payload['candidate_passages'],
    ]);
    app()->instance(EvidenceCheckerService::class, $service);

    $response = $this->postJson(route('projects.evidence.check', $this->project), [
        'claim_text' => 'Klaim untuk uji persistensi assessment.',
    ])->assertOk();

    $claimId = $response->json('claim_id');
    expect($claimId)->not->toBeNull();

    $this->assertDatabaseHas('evidence_assessments', [
        'claim_id' => $claimId,
        'status'   => 'Supported',
    ]);
});

// ---------------------------------------------------------------------------
// Inline Claim Node Persistence — Assessment Retrieval
// ---------------------------------------------------------------------------

test('chapter content persists inline checkedClaim node attributes and can be retrieved', function () {
    $this->actingAs($this->user);

    $chapter = \App\Models\Chapter::factory()->create([
        'project_id' => $this->project->id,
        'title'      => 'BAB I PENDAHULUAN',
        'content'    => null,
    ]);

    $claim = Claim::create([
        'project_id' => $this->project->id,
        'claim_text' => 'Model Random Forest menghasilkan MAE 0.12.',
    ]);

    $tiptapContent = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    [
                        'type'  => 'checkedClaim',
                        'attrs' => [
                            'claim_id'   => $claim->id,
                            'status'     => 'Supported',
                            'claim_text' => 'Model Random Forest menghasilkan MAE 0.12.',
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->patchJson(route('projects.chapters.update', [$this->project, $chapter]), [
        'content' => $tiptapContent,
    ]);

    $response->assertOk();
    $chapter->refresh();
    expect($chapter->content)->toBe($tiptapContent)
        ->and($chapter->content['content'][0]['content'][0]['attrs']['claim_id'])->toBe($claim->id)
        ->and($chapter->content['content'][0]['content'][0]['attrs']['status'])->toBe('Supported');
});

test('student can retrieve a historical evidence assessment by claim id', function () {
    $this->actingAs($this->user);

    $claim = Claim::create([
        'project_id' => $this->project->id,
        'claim_text' => 'Klaim historis untuk ditampilkan kembali.',
    ]);

    EvidenceAssessment::create([
        'claim_id'           => $claim->id,
        'project_id'         => $this->project->id,
        'status'             => 'Contradicted',
        'explanation'        => 'Bukti bertentangan dengan klaim ini.',
        'cited_page_numbers' => [12],
        'candidate_passages' => [],
    ]);

    $response = $this->getJson(route('projects.evidence.show', [$this->project, $claim]));

    $response->assertOk()
        ->assertJsonStructure([
            'claim_id',
            'claim_text',
            'status',
            'explanation',
            'cited_page_numbers',
            'candidate_passages',
            'assessed_at',
        ])
        ->assertJson([
            'claim_id'           => $claim->id,
            'status'             => 'Contradicted',
            'claim_text'         => 'Klaim historis untuk ditampilkan kembali.',
            'cited_page_numbers' => [12],
        ]);
});

// ---------------------------------------------------------------------------
// Tenant Isolation
// ---------------------------------------------------------------------------

test('student cannot access evidence assessments from another project', function () {
    $otherProject = Project::factory()->create(['user_id' => $this->otherUser->id]);

    $claim = Claim::create([
        'project_id' => $otherProject->id,
        'claim_text' => 'Klaim milik proyek lain.',
    ]);

    EvidenceAssessment::create([
        'claim_id'          => $claim->id,
        'project_id'        => $otherProject->id,
        'status'            => 'Supported',
        'explanation'       => 'Tes isolasi tenant.',
        'candidate_passages' => [],
    ]);

    $this->actingAs($this->user);

    $this->getJson(route('projects.evidence.show', [$otherProject, $claim]))
        ->assertForbidden();
});
