<?php

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\Project;
use App\Models\User;
use App\Services\PdfExtractor;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('background job extracts text page-by-page preserving page numbers and transitions to Processed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fixturePath = base_path('tests/Fixtures/sample_paper_2_pages.pdf');
    $storedPath = "projects/{$project->id}/documents/paper_2p.pdf";
    Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

    $document = Document::factory()->queued()->create([
        'project_id' => $project->id,
        'file_path' => $storedPath,
        'title' => 'Sample 2 Pages Paper',
    ]);

    expect($document->status)->toBe(Document::STATUS_QUEUED);

    // Run the job synchronously
    $job = new ProcessDocumentJob($document);
    $job->handle(app(PdfExtractor::class));

    $document->refresh();

    expect($document->status)->toBe(Document::STATUS_PROCESSED);
    expect($document->total_pages)->toBe(2);
    expect($document->error_message)->toBeNull();

    // Verify page-by-page extraction and page numbers
    $pages = $document->pages()->get();
    expect($pages)->toHaveCount(2);

    expect($pages[0]->page_number)->toBe(1);
    expect($pages[0]->content)->toContain('BAB I: Pendahuluan');
    expect($pages[0]->project_id)->toBe($project->id);

    expect($pages[1]->page_number)->toBe(2);
    expect($pages[1]->content)->toContain('BAB II: Tinjauan Pustaka');
    expect($pages[1]->project_id)->toBe($project->id);
});

test('background job transitions to Partial when document contains blank or unreadable pages', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fixturePath = base_path('tests/Fixtures/sample_paper_partial_scanned.pdf');
    $storedPath = "projects/{$project->id}/documents/paper_partial.pdf";
    Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

    $document = Document::factory()->queued()->create([
        'project_id' => $project->id,
        'file_path' => $storedPath,
    ]);

    $job = new ProcessDocumentJob($document);
    $job->handle(app(PdfExtractor::class));

    $document->refresh();

    expect($document->status)->toBe(Document::STATUS_PARTIAL);
    expect($document->total_pages)->toBe(2);
    expect($document->error_message)->not->toBeNull();

    $pages = $document->pages()->get();
    expect($pages)->toHaveCount(2);
    expect($pages[0]->content)->toContain('Halaman Pertama Ada Teks');
    expect($pages[1]->content)->toBe('');
});

test('background job leverages ocr fallback when primary text extraction is blank', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fixturePath = base_path('tests/Fixtures/sample_paper_partial_scanned.pdf');
    $storedPath = "projects/{$project->id}/documents/paper_ocr.pdf";
    Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

    $document = Document::factory()->queued()->create([
        'project_id' => $project->id,
        'file_path' => $storedPath,
    ]);

    /** @var PdfExtractor $extractor */
    $extractor = app(PdfExtractor::class);
    // Inject mock OCR runner for page 2
    $extractor->setOcrRunner(function (string $path, int $page) {
        if ($page === 2) {
            return 'Teks Hasil OCR Halaman 2: Data Statistik Skripsi';
        }

        return null;
    });

    $job = new ProcessDocumentJob($document);
    $job->handle($extractor);

    $document->refresh();

    expect($document->status)->toBe(Document::STATUS_PROCESSED);
    expect($document->total_pages)->toBe(2);

    $pages = $document->pages()->get();
    expect($pages)->toHaveCount(2);
    expect($pages[0]->content)->toContain('Halaman Pertama Ada Teks');
    expect($pages[1]->content)->toContain('Teks Hasil OCR Halaman 2');
});

test('background job transitions to Failed on corrupt pdf', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fixturePath = base_path('tests/Fixtures/corrupt_document.pdf');
    $storedPath = "projects/{$project->id}/documents/corrupt.pdf";
    Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

    $document = Document::factory()->queued()->create([
        'project_id' => $project->id,
        'file_path' => $storedPath,
    ]);

    $job = new ProcessDocumentJob($document);
    $job->handle(app(PdfExtractor::class));

    $document->refresh();

    expect($document->status)->toBe(Document::STATUS_FAILED);
    expect($document->error_message)->not->toBeNull();
    expect($document->pages()->count())->toBe(0);
});

test('background job transitions to Failed when file is missing from storage', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $document = Document::factory()->queued()->create([
        'project_id' => $project->id,
        'file_path' => "projects/{$project->id}/documents/nonexistent.pdf",
    ]);

    $job = new ProcessDocumentJob($document);
    $job->handle(app(PdfExtractor::class));

    $document->refresh();

    expect($document->status)->toBe(Document::STATUS_FAILED);
    expect($document->error_message)->toContain('tidak ditemukan');
});

test('retrying a failed document re-extracts and transitions to Processed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fixturePath = base_path('tests/Fixtures/sample_paper_2_pages.pdf');
    $storedPath = "projects/{$project->id}/documents/paper_retry.pdf";
    Storage::disk('local')->put($storedPath, file_get_contents($fixturePath));

    $document = Document::factory()->failed()->create([
        'project_id' => $project->id,
        'file_path' => $storedPath,
    ]);

    expect($document->status)->toBe(Document::STATUS_FAILED);

    // Reset via retry endpoint logic
    $document->update(['status' => Document::STATUS_QUEUED, 'error_message' => null]);

    $job = new ProcessDocumentJob($document);
    $job->handle(app(PdfExtractor::class));

    $document->refresh();
    expect($document->status)->toBe(Document::STATUS_PROCESSED);
    expect($document->pages()->count())->toBe(2);
});
