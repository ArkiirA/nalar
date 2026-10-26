<?php

use App\Jobs\CompilePdfExportJob;
use App\Models\Chapter;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use App\Services\PdfExportService;
use Illuminate\Support\Facades\Process;

test('pdf export service generates html with campus typography, spacing, and page media', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Pengembangan Sistem Rekomendasi Terdistribusi',
        'citation_style' => 'apa',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'order' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Teks pendahuluan sistem terdistribusi.'],
                    ],
                ],
            ],
        ],
    ]);

    $service = app(PdfExportService::class);
    $html = $service->renderHtml($project);

    expect($html)->toContain('Times New Roman');
    expect($html)->toContain('4cm');
    expect($html)->toContain('3cm');
    expect($html)->toContain('1.5');
    expect($html)->toContain('Pengembangan Sistem Rekomendasi Terdistribusi');
    expect($html)->toContain('BAB I PENDAHULUAN');
    expect($html)->toContain('DAFTAR ISI');
});

test('pdf export generates table of contents, list of tables, and list of figures with page numbers', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Evaluasi Arsitektur Perangkat Lunak',
    ]);

    $ch1 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'order' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Pendahuluan topik.']],
                ],
                [
                    'type' => 'table',
                    'content' => [
                        [
                            'type' => 'tableRow',
                            'content' => [
                                ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Matriks Komparasi']]]]],
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'figureCaption',
                    'content' => [['type' => 'text', 'text' => 'Tabel 1.1 Matriks Komparasi Sistem']],
                ],
                [
                    'type' => 'figureCaption',
                    'content' => [['type' => 'text', 'text' => 'Gambar 1.1 Diagram Alir Sistem']],
                ],
            ],
        ],
    ]);

    $subch = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $ch1->id,
        'title' => '1.1 Latar Belakang Masalah',
        'order' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Latar belakang penelitian.']]],
            ],
        ],
    ]);

    $service = app(PdfExportService::class);
    $html = $service->renderHtml($project);

    expect($html)->toContain('DAFTAR ISI');
    expect($html)->toContain('BAB I PENDAHULUAN');
    expect($html)->toContain('1.1 Latar Belakang Masalah');
    expect($html)->toContain('toc-dots');
    expect($html)->toContain('toc-page');
    expect($html)->toContain('DAFTAR TABEL');
    expect($html)->toContain('Tabel 1.1 Matriks Komparasi Sistem');
    expect($html)->toContain('DAFTAR GAMBAR');
    expect($html)->toContain('Gambar 1.1 Diagram Alir Sistem');

    // Root chapter (BAB I) has page break before it
    expect($html)->toMatch('/<div class="page-break"><\/div>\s*<div class="chapter-content" id="chapter-'.$ch1->id.'">/');

    // Subchapter does NOT have a page break before it
    expect($html)->not->toMatch('/<div class="page-break"><\/div>\s*<div class="chapter-content" id="chapter-'.$subch->id.'">/');
});

test('pdf export service formats bibliography with hanging indent for cited references', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Analisis Algoritma Optimasi',
        'citation_style' => 'apa',
    ]);

    $ref = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Optimization Techniques in Machine Learning',
        'authors' => [['family' => 'Goodfellow', 'given' => 'Ian']],
        'year' => 2016,
    ]);

    $uncitedRef = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Uncited Deep Learning Textbook',
        'authors' => [['family' => 'UncitedWriter', 'given' => 'Bob']],
        'year' => 2019,
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Merujuk pada '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $ref->id,
                                'label' => '(Goodfellow, 2016)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $service = app(PdfExportService::class);
    $html = $service->renderHtml($project);

    expect($html)->toContain('DAFTAR PUSTAKA');
    expect($html)->toContain('Goodfellow');
    expect($html)->toContain('Optimization Techniques in Machine Learning');
    expect($html)->not->toContain('UncitedWriter');
});

test('pdf export service compiles pdf via Process::fake', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Pengujian Process Fake PDF',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten bab 1.']]],
            ],
        ],
    ]);

    Process::fake([
        '*' => function ($process) {
            // Find --print-to-pdf argument and create file
            foreach ($process->command as $arg) {
                if (str_starts_with($arg, '--print-to-pdf=')) {
                    $path = substr($arg, strlen('--print-to-pdf='));
                    file_put_contents($path, '%PDF-1.4 process fake content');
                }
            }

            return Process::result('', '', 0);
        },
    ]);

    $service = app(PdfExportService::class);
    $pdfPath = $service->generate($project);

    expect(file_exists($pdfPath))->toBeTrue();
    expect(file_get_contents($pdfPath))->toContain('%PDF-1.4 process fake content');

    @unlink($pdfPath);
});

test('compile pdf export job generates pdf to custom output path', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Pengujian Job Kompilasi PDF',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten job.']]],
            ],
        ],
    ]);

    $outputPath = storage_path('app/test_job_export_'.uniqid().'.pdf');

    Process::fake([
        '*' => function ($process) use ($outputPath) {
            file_put_contents($outputPath, '%PDF-1.4 queued job pdf');

            return Process::result('', '', 0);
        },
    ]);

    $job = new CompilePdfExportJob($project, $outputPath);
    $resultPath = $job->handle(app(PdfExportService::class));

    expect($resultPath)->toBe($outputPath);
    expect(file_exists($outputPath))->toBeTrue();
    expect(file_get_contents($outputPath))->toContain('%PDF-1.4 queued job pdf');

    @unlink($outputPath);
});

test('export pdf endpoint downloads file with correct headers for authorized student', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Analisis Performa Model AI',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks bab satu.']]],
            ],
        ],
    ]);

    $service = app(PdfExportService::class);
    $service->setRunner(function (string $htmlPath, string $pdfPath) {
        file_put_contents($pdfPath, '%PDF-1.4 test stream');

        return true;
    });
    $this->instance(PdfExportService::class, $service);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/export/pdf");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('.pdf');
});

test('export pdf endpoint forbids unauthorized users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->get("/projects/{$project->id}/export/pdf");
    $response->assertForbidden();
});

test('pdf export service generates actual pdf with real headless chromium if available', function () {
    $binary = env('CHROMIUM_BINARY') ?? config('services.chromium.binary');
    if (! $binary || ! file_exists($binary) || ! is_executable($binary)) {
        $this->markTestSkipped('No Chromium binary configured for real test');
    }

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Skripsi Komputasi Awan',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pendahuluan komputasi awan.']]],
            ],
        ],
    ]);

    $service = app(PdfExportService::class);
    $pdfPath = $service->generate($project);

    expect(file_exists($pdfPath))->toBeTrue();
    expect(filesize($pdfPath))->toBeGreaterThan(1000);

    // Verify it is a valid PDF
    $header = file_get_contents($pdfPath, false, null, 0, 10);
    expect($header)->toContain('%PDF-');

    @unlink($pdfPath);
});
