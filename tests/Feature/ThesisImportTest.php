<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use App\Services\PdfExtractor;
use App\Services\ThesisImporterService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Helper to generate a temporary DOCX file with specified sections/headings.
 */
function createTestDocx(array $sections): string
{
    $phpWord = new PhpWord();
    $phpWord->addTitleStyle(1, ['size' => 14, 'bold' => true]);
    $phpWord->addTitleStyle(2, ['size' => 12, 'bold' => true]);

    $section = $phpWord->addSection();

    foreach ($sections as $item) {
        $type = $item['type'] ?? 'text';
        if ($type === 'title') {
            $section->addTitle($item['text'], $item['level'] ?? 1);
        } else {
            $section->addText($item['text']);
        }
    }

    $filePath = tempnam(sys_get_temp_dir(), 'test_thesis_').'.docx';
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($filePath);

    return $filePath;
}

test('file upload endpoint accepts valid docx and pdf thesis drafts up to 50mb', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $docxPath = createTestDocx([
        ['type' => 'title', 'text' => 'BAB I PENDAHULUAN', 'level' => 1],
        ['type' => 'text', 'text' => 'Latar belakang penelitian ini bertujuan untuk mengevaluasi efektivitas.'],
    ]);

    $uploadedDocx = new UploadedFile($docxPath, 'thesis_draft.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/import/parse", [
            'file' => $uploadedDocx,
        ]);

    @unlink($docxPath);

    $response->assertOk();
    $response->assertJsonStructure([
        'message',
        'candidate_chapters' => [
            '*' => ['id', 'title', 'preview_text', 'content', 'selected'],
        ],
    ]);

    expect($response->json('candidate_chapters'))->toHaveCount(1);
    expect($response->json('candidate_chapters.0.title'))->toBe('BAB I PENDAHULUAN');
});

test('file upload endpoint rejects files exceeding 50mb or invalid extensions', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Exceeds 50MB (52MB)
    $oversizedFile = UploadedFile::fake()->create('huge_draft.docx', 52 * 1024, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/import/parse", [
            'file' => $oversizedFile,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('file');

    // Invalid extension (.txt)
    $invalidExtFile = UploadedFile::fake()->create('draft.txt', 500, 'text/plain');

    $response2 = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/import/parse", [
            'file' => $invalidExtFile,
        ]);

    $response2->assertStatus(422);
    $response2->assertJsonValidationErrors('file');
});

test('import parse endpoint enforces tenant isolation and project authorization', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $file = UploadedFile::fake()->create('thesis.docx', 200);

    $response = $this->actingAs($stranger)
        ->postJson("/projects/{$project->id}/import/parse", [
            'file' => $file,
        ]);

    $response->assertForbidden();
});

test('parser splits docx draft at recognized chapter heading patterns and heading 1 styles', function () {
    $docxPath = createTestDocx([
        ['type' => 'text', 'text' => 'Draf Skripsi Awal Mahasiswa.'],
        ['type' => 'title', 'text' => 'BAB I PENDAHULUAN', 'level' => 1],
        ['type' => 'text', 'text' => 'Paragraf pertama latar belakang masalah.'],
        ['type' => 'text', 'text' => 'Paragraf kedua rumusan masalah.'],
        ['type' => 'title', 'text' => 'BAB II TINJAUAN PUSTAKA', 'level' => 1],
        ['type' => 'text', 'text' => 'Landasan teori dan kajian literatur terkait.'],
        ['type' => 'title', 'text' => 'BAB III METODOLOGI PENELITIAN', 'level' => 1],
        ['type' => 'text', 'text' => 'Metode yang digunakan adalah eksperimen kuantitatif.'],
    ]);

    $service = app(ThesisImporterService::class);
    $candidates = $service->parseFile($docxPath, 'docx');

    @unlink($docxPath);

    // Should detect Bagian Awal (pre-heading) + BAB I + BAB II + BAB III
    expect($candidates)->toHaveCount(4);
    expect($candidates[0]['title'])->toBe('Bagian Awal');
    expect($candidates[1]['title'])->toBe('BAB I PENDAHULUAN');
    expect($candidates[2]['title'])->toBe('BAB II TINJAUAN PUSTAKA');
    expect($candidates[3]['title'])->toBe('BAB III METODOLOGI PENELITIAN');

    // Split integrity check: verify preview_text and content
    expect($candidates[1]['preview_text'])->toContain('Paragraf pertama latar belakang masalah.');
    expect($candidates[1]['preview_text'])->toContain('Paragraf kedua rumusan masalah.');
    expect($candidates[1]['content']['type'])->toBe('doc');
    expect($candidates[1]['content']['content'])->toBeArray();

    // Verify first node is heading with level 1
    expect($candidates[1]['content']['content'][0]['type'])->toBe('heading');
    expect($candidates[1]['content']['content'][0]['attrs']['level'])->toBe(1);
    expect($candidates[1]['content']['content'][0]['content'][0]['text'])->toBe('BAB I PENDAHULUAN');
});

test('parser combines two-line chapter markers like BAB I followed by PENDAHULUAN', function () {
    $docxPath = createTestDocx([
        ['type' => 'title', 'text' => 'BAB I', 'level' => 1],
        ['type' => 'text', 'text' => 'PENDAHULUAN'],
        ['type' => 'text', 'text' => 'Latar belakang penelitian dimulai di sini.'],
        ['type' => 'title', 'text' => 'BAB II', 'level' => 1],
        ['type' => 'text', 'text' => 'TINJAUAN PUSTAKA'],
        ['type' => 'text', 'text' => 'Kajian literatur diuraikan di sini.'],
    ]);

    $service = app(ThesisImporterService::class);
    $candidates = $service->parseFile($docxPath, 'docx');

    @unlink($docxPath);

    expect($candidates)->toHaveCount(2);
    expect($candidates[0]['title'])->toBe('BAB I PENDAHULUAN');
    expect($candidates[0]['preview_text'])->toContain('Latar belakang penelitian dimulai di sini.');
    expect($candidates[1]['title'])->toBe('BAB II TINJAUAN PUSTAKA');
    expect($candidates[1]['preview_text'])->toContain('Kajian literatur diuraikan di sini.');
});

test('parser detects chapter headers from text-based pdf drafts', function () {
    $pdfPath = base_path('tests/Fixtures/sample_paper_2_pages.pdf');

    $service = app(ThesisImporterService::class);
    $candidates = $service->parseFile($pdfPath, 'pdf');

    expect($candidates)->toBeArray();
    expect(count($candidates))->toBeGreaterThanOrEqual(2);

    $titles = array_column($candidates, 'title');
    expect(implode(' ', $titles))->toContain('BAB I');
    expect(implode(' ', $titles))->toContain('BAB II');
});

test('on confirmation, creates chapter documents and updates outline without overwriting existing items', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Existing pre-existing chapter in outline
    $existingChapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => null,
        'title' => 'Bab Pendahuluan Awal',
        'content' => ['type' => 'doc', 'content' => []],
        'order' => 1,
    ]);

    $payload = [
        'chapters' => [
            [
                'title' => 'BAB I PENDAHULUAN',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'BAB I PENDAHULUAN']]],
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks bab 1 baru diimpor.']]],
                    ],
                ],
            ],
            [
                'title' => 'BAB II TINJAUAN PUSTAKA',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'BAB II TINJAUAN PUSTAKA']]],
                        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks bab 2 baru diimpor.']]],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/import/confirm", $payload);

    $response->assertCreated();
    $response->assertJsonPath('message', '2 bab berhasil diimpor ke dalam outline proyek.');

    // Verify existing chapter was NOT overwritten or deleted
    $existingChapter->refresh();
    expect($existingChapter->title)->toBe('Bab Pendahuluan Awal');
    expect($existingChapter->order)->toBe(1);

    // Verify new chapters were created with sequential order
    $allChapters = $project->chapters()->whereNull('parent_id')->orderBy('order')->get();
    expect($allChapters)->toHaveCount(3);

    expect($allChapters[0]->id)->toBe($existingChapter->id);
    expect($allChapters[1]->title)->toBe('BAB I PENDAHULUAN');
    expect($allChapters[1]->order)->toBe(2);
    expect($allChapters[1]->content['content'][1]['content'][0]['text'])->toBe('Teks bab 1 baru diimpor.');

    expect($allChapters[2]->title)->toBe('BAB II TINJAUAN PUSTAKA');
    expect($allChapters[2]->order)->toBe(3);
    expect($allChapters[2]->content['content'][1]['content'][0]['text'])->toBe('Teks bab 2 baru diimpor.');
});

test('confirmation rejects empty chapter list or unauthorized user', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    // Unauthorized user
    $response = $this->actingAs($stranger)
        ->postJson("/projects/{$project->id}/import/confirm", [
            'chapters' => [
                ['title' => 'BAB I', 'content' => null],
            ],
        ]);
    $response->assertForbidden();

    // Empty chapters array
    $response2 = $this->actingAs($owner)
        ->postJson("/projects/{$project->id}/import/confirm", [
            'chapters' => [],
        ]);
    $response2->assertStatus(422);
    $response2->assertJsonValidationErrors('chapters');
});
