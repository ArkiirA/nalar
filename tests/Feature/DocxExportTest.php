<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use App\Services\DocxExportService;
use PhpOffice\PhpWord\Shared\Converter;

test('docx export compiles chapters in outline order with chapter page breaks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Implementasi Sistem Pengenalan Citra',
        'citation_style' => 'apa',
    ]);

    $ref = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Deep Residual Learning for Image Recognition',
        'authors' => [['family' => 'He', 'given' => 'Kaiming']],
        'year' => 2016,
        'publication_title' => 'CVPR',
        'pages' => '770-778',
    ]);

    // BAB I with sections 1.1 and 1.2
    $bab1 = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'title' => 'BAB I PENDAHULUAN',
        'order' => 1,
        'content' => null,
    ]);

    $sec11 = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $bab1->id,
        'title' => '1.1 Latar Belakang Masalah',
        'order' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Perkembangan kecerdasan buatan '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $ref->id,
                                'label' => '(He, 2016)',
                            ],
                        ],
                        ['type' => 'text', 'text' => ' sangat pesat.'],
                    ],
                ],
            ],
        ],
    ]);

    $sec12 = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $bab1->id,
        'title' => '1.2 Rumusan Masalah',
        'order' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Bagaimana efektivitas model resnet?'],
                    ],
                ],
            ],
        ],
    ]);

    // BAB II
    $bab2 = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'title' => 'BAB II TINJAUAN PUSTAKA',
        'order' => 2,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Teori jaringan saraf tiruan dibahas pada bab ini.'],
                    ],
                ],
            ],
        ],
    ]);

    $service = app(DocxExportService::class);
    $filePath = $service->generate($project);

    expect(file_exists($filePath))->toBeTrue();
    expect(filesize($filePath))->toBeGreaterThan(1000);

    // Inspect document XML inside docx zip archive
    $zip = new ZipArchive();
    $opened = $zip->open($filePath);
    expect($opened)->toBeTrue();

    $docXml = $zip->getFromName('word/document.xml');
    $stylesXml = $zip->getFromName('word/styles.xml');
    $zip->close();
    @unlink($filePath);

    // Verify sequential chapter title stitching in document.xml
    expect($docXml)->toContain('BAB I PENDAHULUAN');
    expect($docXml)->toContain('1.1 Latar Belakang Masalah');
    expect($docXml)->toContain('Perkembangan kecerdasan buatan');
    expect($docXml)->toContain('(He, 2016)');
    expect($docXml)->toContain('1.2 Rumusan Masalah');
    expect($docXml)->toContain('BAB II TINJAUAN PUSTAKA');

    // Verify chapter page break exists between BAB I and BAB II
    expect($docXml)->toContain('<w:br w:type="page"/>');

    // Verify bibliography section "DAFTAR PUSTAKA" and citation consistency
    expect($docXml)->toContain('DAFTAR PUSTAKA');
    expect($docXml)->toContain('He, K.');
    expect($docXml)->toContain('Deep Residual Learning for Image Recognition');

    // Verify font specification (Times New Roman) and margins
    // 4cm = ~2268 twips, 3cm = ~1701 twips
    expect($docXml)->toContain('w:top="2268"');
    expect($docXml)->toContain('w:left="2268"');
    expect($docXml)->toContain('w:bottom="1701"');
    expect($docXml)->toContain('w:right="1701"');

    // Verify Times New Roman in styles or document
    expect($stylesXml . $docXml)->toContain('Times New Roman');
});

test('docx export formats bibliography in harvard style when selected', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Sistem Informasi Rumah Sakit',
        'citation_style' => 'harvard',
    ]);

    $ref = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Database Design Systems',
        'authors' => [['family' => 'Smith', 'given' => 'John']],
        'year' => 2021,
        'publisher' => 'Academic Press',
    ]);

    $uncitedRef = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Uncited Methodologies Book',
        'authors' => [['family' => 'UncitedAuthor', 'given' => 'Alice']],
        'year' => 2020,
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'title' => 'BAB I PENDAHULUAN',
        'order' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Berdasarkan '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $ref->id,
                                'label' => '(Smith, 2021)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $service = app(DocxExportService::class);
    $filePath = $service->generate($project);

    $zip = new ZipArchive();
    $zip->open($filePath);
    $docXml = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($filePath);

    expect($docXml)->toContain('DAFTAR PUSTAKA');
    expect($docXml)->toContain('Smith, J.');
    expect($docXml)->toContain('Database Design Systems');
    expect($docXml)->not->toContain('UncitedAuthor');
    expect($docXml)->not->toContain('Uncited Methodologies Book');
});

test('export docx endpoint downloads file with correct headers for authorized student', function () {
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
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Teks pendahuluan bab satu.'],
                    ],
                ],
            ],
        ],
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}/export/docx");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    expect($response->headers->get('Content-Disposition'))->toContain('.docx');
});

test('export docx endpoint forbids unauthorized users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->get("/projects/{$project->id}/export/docx");
    $response->assertForbidden();
});
