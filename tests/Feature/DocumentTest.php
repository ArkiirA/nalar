<?php

use App\Jobs\ProcessPdfDocument;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\Document\PdfExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('guest cannot list or upload documents', function () {
    $project = Project::factory()->create();

    $this->getJson(route('projects.documents.index', $project))->assertUnauthorized();
    $this->postJson(route('projects.documents.store', $project))->assertUnauthorized();
});

test('unauthorized user cannot list or upload documents on foreign project', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($other)->getJson(route('projects.documents.index', $project))->assertForbidden();
    $this->actingAs($other)->postJson(route('projects.documents.store', $project))->assertForbidden();
});

test('upload rejects non-pdf file and validates maximum size', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $textDoc = UploadedFile::fake()->create('paper.txt', 100, 'text/plain');

    $this->actingAs($user)->postJson(route('projects.documents.store', $project), [
        'file' => $textDoc,
    ])->assertUnprocessable()->assertJsonValidationErrors(['file']);
});

test('authorized user successfully uploads pdf document and dispatches extraction job', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $pdfFile = UploadedFile::fake()->create('jurnal_machine_learning.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)->postJson(route('projects.documents.store', $project), [
        'file' => $pdfFile,
        'title' => 'Studi Komparasi CNN vs ViT',
    ]);

    $response->assertCreated()
        ->assertJsonPath('document.title', 'Studi Komparasi CNN vs ViT')
        ->assertJsonPath('document.original_filename', 'jurnal_machine_learning.pdf')
        ->assertJsonPath('document.status', Document::STATUS_QUEUED);

    $this->assertDatabaseHas('documents', [
        'project_id' => $project->id,
        'title' => 'Studi Komparasi CNN vs ViT',
        'status' => Document::STATUS_QUEUED,
    ]);

    Queue::assertPushed(ProcessPdfDocument::class);
});

test('pdf extraction service processes document and tags physical pages', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Create a minimal valid 1-page PDF
    $rawPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Resources<<>>>>endobj\nxref\n0 4\n0000000000 65535 f \n0000000009 00000 n \n0000000052 00000 n \n0000000101 00000 n \ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n178\n%%EOF";
    $filePath = "projects/{$project->id}/documents/test_paper.pdf";
    Storage::disk('local')->put($filePath, $rawPdf);

    $document = Document::create([
        'project_id' => $project->id,
        'title' => 'Test PDF',
        'original_filename' => 'test_paper.pdf',
        'file_path' => $filePath,
        'file_size' => strlen($rawPdf),
        'mime_type' => 'application/pdf',
        'status' => Document::STATUS_QUEUED,
    ]);

    $service = new PdfExtractionService();
    $processed = $service->extract($document);

    expect($processed->page_count)->toBe(1)
        ->and($processed->pages_data)->toBeArray()
        ->and($processed->pages_data[0]['page'])->toBe(1);
});

test('user can retry extraction and delete document', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $filePath = "projects/{$project->id}/documents/paper.pdf";
    Storage::disk('local')->put($filePath, 'dummy pdf');

    $document = Document::create([
        'project_id' => $project->id,
        'title' => 'Paper to retry',
        'original_filename' => 'paper.pdf',
        'file_path' => $filePath,
        'file_size' => 10,
        'status' => Document::STATUS_FAILED,
    ]);

    // Retry
    $retryResponse = $this->actingAs($user)->postJson(route('projects.documents.retry', [$project, $document]));
    $retryResponse->assertOk()->assertJsonPath('document.status', Document::STATUS_QUEUED);
    Queue::assertPushed(ProcessPdfDocument::class);

    // Delete
    $deleteResponse = $this->actingAs($user)->deleteJson(route('projects.documents.destroy', [$project, $document]));
    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($filePath);
});
