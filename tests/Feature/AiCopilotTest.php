<?php

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();

    $this->project = Project::factory()->create([
        'user_id' => $this->user->id,
        'ai_consent' => false,
        'ai_consent_at' => null,
    ]);
});

test('unauthenticated users cannot access ai copilot endpoints', function () {
    $this->postJson(route('projects.ai.consent', $this->project), ['consent' => true])
        ->assertUnauthorized();

    $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Test',
        'mode' => 'exploratory',
    ])->assertUnauthorized();

    $this->postJson(route('projects.ai.proposal', $this->project), [
        'selected_text' => 'Test text',
    ])->assertUnauthorized();
});

test('unauthorized users cannot access another users project ai endpoints', function () {
    $this->actingAs($this->otherUser);

    $this->postJson(route('projects.ai.consent', $this->project), ['consent' => true])
        ->assertForbidden();

    $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Halo AI',
        'mode' => 'exploratory',
    ])->assertForbidden();

    $this->postJson(route('projects.ai.proposal', $this->project), [
        'selected_text' => 'Teks skripsi orang lain',
    ])->assertForbidden();
});

test('ai chat and proposal endpoints are strictly blocked before consent is granted', function () {
    $this->actingAs($this->user);

    $this->assertFalse($this->project->fresh()->hasAiConsent());

    $chatResponse = $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Bagaimana menyusun latar belakang?',
        'mode' => 'exploratory',
    ]);

    $chatResponse->assertStatus(403)
        ->assertJson([
            'error' => 'ai_consent_required',
        ]);

    $proposalResponse = $this->postJson(route('projects.ai.proposal', $this->project), [
        'selected_text' => 'Kalimat ini perlu direvisi.',
        'instruction' => 'Buat lebih formal',
    ]);

    $proposalResponse->assertStatus(403)
        ->assertJson([
            'error' => 'ai_consent_required',
        ]);
});

test('student can grant explicit ai processing consent', function () {
    $this->actingAs($this->user);

    $response = $this->postJson(route('projects.ai.consent', $this->project), [
        'consent' => true,
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'ai_consent' => true,
        ]);

    $freshProject = $this->project->fresh();
    expect($freshProject->ai_consent)->toBeTrue()
        ->and($freshProject->ai_consent_at)->not->toBeNull();
});

test('student can chat in exploratory mode and receives verification warning', function () {
    $this->actingAs($this->user);
    $this->project->grantAiConsent();

    $response = $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Apa ide rumusan masalah tentang machine learning untuk prediksi curah hujan?',
        'mode' => 'exploratory',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'reply',
            'mode',
            'candidate_passages',
            'warning',
        ])
        ->assertJson([
            'mode' => 'exploratory',
        ]);

    expect($response->json('warning'))->toContain('Exploratory Mode');
});

test('student can chat in evidence-based mode and receives grounded candidate passages', function () {
    $this->actingAs($this->user);
    $this->project->grantAiConsent();

    $document = Document::create([
        'project_id' => $this->project->id,
        'title' => 'Algoritma Prediksi Cuaca.pdf',
        'original_filename' => 'cuaca.pdf',
        'file_path' => 'documents/cuaca.pdf',
        'file_size' => 2048,
        'mime_type' => 'application/pdf',
        'status' => 'Completed',
    ]);

    DocumentChunk::create([
        'project_id' => $this->project->id,
        'document_id' => $document->id,
        'page_number' => 7,
        'chunk_index' => 0,
        'content' => 'Model Random Forest menghasilkan MAE sebesar 0.12 pada data curah hujan tahunan.',
    ]);

    $response = $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Berapa nilai MAE yang dihasilkan model cuaca?',
        'mode' => 'evidence_based',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'reply',
            'mode',
            'candidate_passages',
            'warning',
        ])
        ->assertJson([
            'mode' => 'evidence_based',
            'warning' => null,
        ]);

    expect($response->json('candidate_passages'))->not->toBeEmpty()
        ->and($response->json('candidate_passages.0.document_title'))->toBe('Algoritma Prediksi Cuaca.pdf')
        ->and($response->json('candidate_passages.0.page_number'))->toBe(7);
});

test('evidence-based mode strictly refuses to invent facts when research library is empty', function () {
    $this->actingAs($this->user);
    $this->project->grantAiConsent();

    // No documents uploaded in this project
    $response = $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Berapa nilai MAE yang dihasilkan?',
        'mode' => 'evidence_based',
    ]);

    $response->assertOk()
        ->assertJson([
            'mode' => 'evidence_based',
            'candidate_passages' => [],
        ]);

    expect($response->json('reply'))->toMatch('/tidak memiliki dokumen|lacks supporting evidence|menolak/i');
});

test('proposal endpoint returns side-by-side diff payload with additions and deletions', function () {
    $this->actingAs($this->user);
    $this->project->grantAiConsent();

    $response = $this->postJson(route('projects.ai.proposal', $this->project), [
        'selected_text' => 'Penelitian ini bertujuan untuk menganalisis efektivitas sistem informasi.',
        'instruction' => 'Buat lebih akademis dan mendalam',
        'mode' => 'exploratory',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'original_text',
            'proposed_text',
            'mode',
            'diff' => [
                'original',
                'proposed',
                'has_changes',
                'chunks',
                'stats' => ['additions', 'deletions', 'unchanged'],
            ],
            'explanation',
        ]);

    expect($response->json('diff.has_changes'))->toBeTrue();
});

test('proposal endpoint in evidence-based mode strictly refuses when research library is empty', function () {
    $this->actingAs($this->user);
    $this->project->grantAiConsent();

    $original = 'Penelitian ini menggunakan sampel 100 responden.';
    $response = $this->postJson(route('projects.ai.proposal', $this->project), [
        'selected_text' => $original,
        'instruction' => 'Cek bukti sampel di literatur',
        'mode' => 'evidence_based',
    ]);

    $response->assertOk()
        ->assertJson([
            'original_text' => $original,
            'proposed_text' => $original,
            'mode' => 'evidence_based',
            'candidate_passages' => [],
            'diff' => [
                'has_changes' => false,
            ],
        ]);

    expect($response->json('explanation'))->toMatch('/Penolakan|lacks supporting evidence|tidak memiliki candidate passage/i');
});

test('student can revoke consent at any time immediately halting external ai processing', function () {
    $this->actingAs($this->user);
    $this->project->grantAiConsent();

    // Verify chat works with consent
    $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Halo AI',
        'mode' => 'exploratory',
    ])->assertOk();

    // Revoke consent
    $revokeResponse = $this->postJson(route('projects.ai.consent', $this->project), [
        'consent' => false,
    ]);

    $revokeResponse->assertOk()
        ->assertJson([
            'success' => true,
            'ai_consent' => false,
        ]);

    $freshProject = $this->project->fresh();
    expect($freshProject->ai_consent)->toBeFalse()
        ->and($freshProject->ai_consent_at)->toBeNull();

    // Subsequent chat immediately blocked
    $this->postJson(route('projects.ai.chat', $this->project), [
        'message' => 'Pertanyaan setelah consent dicabut',
        'mode' => 'exploratory',
    ])->assertStatus(403)
        ->assertJson([
            'error' => 'ai_consent_required',
        ]);
});
