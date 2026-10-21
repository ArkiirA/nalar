<?php

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\DocumentPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('authenticated user can upload a valid pdf up to 25mb scoped to their project', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->create('sample_paper.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/documents", [
            'file' => $file,
            'title' => 'Sample Research Paper',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('documents', [
        'project_id' => $project->id,
        'title' => 'Sample Research Paper',
        'original_filename' => 'sample_paper.pdf',
        'mime_type' => 'application/pdf',
        'status' => Document::STATUS_QUEUED,
    ]);

    $document = Document::where('project_id', $project->id)->first();
    expect($document->file_path)->toStartWith("projects/{$project->id}/documents/");
    Storage::assertExists($document->file_path);

    Queue::assertPushed(ProcessDocumentJob::class, function ($job) use ($document) {
        return $job->document->id === $document->id;
    });
});

test('uploader rejects non-pdf files', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->create('notes.txt', 50, 'text/plain');

    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/documents", [
            'file' => $file,
        ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

test('uploader rejects files exceeding 25mb', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // 25MB = 25600 KB. 26000 KB exceeds 25MB limit.
    $oversizedFile = UploadedFile::fake()->create('giant_book.pdf', 26000, 'application/pdf');

    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/documents", [
            'file' => $oversizedFile,
        ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

test('uploader defaults title to original filename without extension when title omitted', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $file = UploadedFile::fake()->create('analisis_data_skripsi.pdf', 500, 'application/pdf');

    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/documents", [
            'file' => $file,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('documents', [
        'project_id' => $project->id,
        'title' => 'analisis_data_skripsi',
        'original_filename' => 'analisis_data_skripsi.pdf',
    ]);
});

test('user cannot upload document to another users project', function () {
    Storage::fake('local');
    Queue::fake();

    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $file = UploadedFile::fake()->create('paper.pdf', 100, 'application/pdf');

    $response = $this->actingAs($attacker)
        ->post("/projects/{$project->id}/documents", [
            'file' => $file,
        ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('documents', 0);
    Queue::assertNothingPushed();
});

test('user can retry a failed or partial document processing job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->failed()->create([
        'project_id' => $project->id,
    ]);

    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/documents/{$document->id}/retry");

    $response->assertRedirect();

    $document->refresh();
    expect($document->status)->toBe(Document::STATUS_QUEUED);
    expect($document->error_message)->toBeNull();

    Queue::assertPushed(ProcessDocumentJob::class, function ($job) use ($document) {
        return $job->document->id === $document->id;
    });
});

test('user cannot retry a document belonging to another users project', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);
    $document = Document::factory()->failed()->create([
        'project_id' => $project->id,
    ]);

    $response = $this->actingAs($attacker)
        ->post("/projects/{$project->id}/documents/{$document->id}/retry");

    $response->assertForbidden();
    Queue::assertNothingPushed();
});

test('user can delete their document and physical file is removed', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $filePath = "projects/{$project->id}/documents/paper.pdf";
    Storage::disk('local')->put($filePath, 'fake pdf content');

    $document = Document::factory()->create([
        'project_id' => $project->id,
        'file_path' => $filePath,
    ]);

    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
    ]);

    Storage::assertExists($filePath);

    $response = $this->actingAs($user)
        ->delete("/projects/{$project->id}/documents/{$document->id}");

    $response->assertRedirect();

    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    $this->assertDatabaseMissing('document_pages', ['document_id' => $document->id]);
    Storage::assertMissing($filePath);
});

test('user cannot delete another users document', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);
    $document = Document::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($attacker)
        ->delete("/projects/{$project->id}/documents/{$document->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('documents', ['id' => $document->id]);
});

test('user can fetch extracted pages for their document', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $document = Document::factory()->processed()->create([
        'project_id' => $project->id,
        'total_pages' => 2,
    ]);

    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 1,
        'content' => 'Teks halaman pertama',
    ]);

    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $document->id,
        'page_number' => 2,
        'content' => 'Teks halaman kedua',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/projects/{$project->id}/documents/{$document->id}/pages");

    $response->assertOk();
    $response->assertJsonCount(2, 'pages');
    $response->assertJsonPath('pages.0.page_number', 1);
    $response->assertJsonPath('pages.0.content', 'Teks halaman pertama');
    $response->assertJsonPath('pages.1.page_number', 2);
    $response->assertJsonPath('pages.1.content', 'Teks halaman kedua');
});

test('user cannot fetch extracted pages from another users project', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);
    $document = Document::factory()->processed()->create([
        'project_id' => $project->id,
    ]);

    $response = $this->actingAs($attacker)
        ->getJson("/projects/{$project->id}/documents/{$document->id}/pages");

    $response->assertForbidden();
});

test('project show includes documents prop with pages count', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $doc = Document::factory()->processed()->create([
        'project_id' => $project->id,
        'title' => 'Studi Kasus AI',
        'status' => Document::STATUS_PROCESSED,
    ]);

    DocumentPage::factory()->create([
        'project_id' => $project->id,
        'document_id' => $doc->id,
        'page_number' => 1,
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('documents', 1)
        ->where('documents.0.id', $doc->id)
        ->where('documents.0.title', 'Studi Kasus AI')
        ->where('documents.0.status', Document::STATUS_PROCESSED)
        ->where('documents.0.pages_count', 1)
    );
});
