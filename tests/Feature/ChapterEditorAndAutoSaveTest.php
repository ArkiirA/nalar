<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;

test('student can auto-save chapter content payload via json patch', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => null,
    ]);

    $tiptapContent = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'heading',
                'attrs' => ['level' => 1],
                'content' => [
                    ['type' => 'text', 'text' => 'BAB I PENDAHULUAN'],
                ],
            ],
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Penelitian ini bertujuan untuk menganalisis efektivitas sistem.'],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => $tiptapContent,
        ]);

    $response->assertOk();
    $response->assertJsonPath('chapter.id', $chapter->id);

    $chapter->refresh();
    expect($chapter->title)->toBe('BAB I PENDAHULUAN');
    expect($chapter->content)->toBe($tiptapContent);
});

test('chapter auto-save enforces tenant and project isolation', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => null,
    ]);

    $payload = [
        'content' => [
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hacked content']]],
            ],
        ],
    ];

    // Attacker cannot patch owner's chapter
    $response = $this->actingAs($attacker)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", $payload);

    $response->assertForbidden();
    expect($chapter->fresh()->content)->toBeNull();

    // Mismatched project ID returns 404
    $otherProject = Project::factory()->create(['user_id' => $owner->id]);
    $mismatchedResponse = $this->actingAs($owner)
        ->patchJson("/projects/{$otherProject->id}/chapters/{$chapter->id}", $payload);

    $mismatchedResponse->assertNotFound();
});

test('chapter content saving is isolated across chapters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $chapter1 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten Bab 1 asli']]]],
        ],
    ]);

    $chapter2 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB II TINJAUAN PUSTAKA',
        'content' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten Bab 2 asli']]]],
        ],
    ]);

    $newBab1Content = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten Bab 1 diperbarui']]]],
    ];

    $response = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter1->id}", [
            'content' => $newBab1Content,
        ]);

    $response->assertOk();

    expect($chapter1->fresh()->content)->toBe($newBab1Content);
    expect($chapter2->fresh()->content)->toBe([
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten Bab 2 asli']]]],
    ]);
});

test('updating title preserves existing content and auto-saving content preserves title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $initialContent = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Paragraf awal']]]],
    ];

    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'Judul Awal',
        'content' => $initialContent,
    ]);

    // Update title only
    $response1 = $this->actingAs($user)
        ->patch("/projects/{$project->id}/chapters/{$chapter->id}", [
            'title' => 'Judul Baru',
        ]);
    $response1->assertRedirect();

    $chapter->refresh();
    expect($chapter->title)->toBe('Judul Baru');
    expect($chapter->content)->toBe($initialContent);

    // Update content only
    $updatedContent = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Paragraf diubah']]]],
    ];

    $response2 = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => $updatedContent,
        ]);
    $response2->assertOk();

    $chapter->refresh();
    expect($chapter->title)->toBe('Judul Baru');
    expect($chapter->content)->toBe($updatedContent);
});

test('chapter document persists rich academic structures including headings, lists, tables, and figure captions', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB III METODOLOGI PENELITIAN',
    ]);

    $academicDocument = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'heading',
                'attrs' => ['level' => 1],
                'content' => [['type' => 'text', 'text' => 'BAB III METODOLOGI PENELITIAN']],
            ],
            [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [['type' => 'text', 'text' => '3.1 Desain Penelitian']],
            ],
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Penelitian ini menggunakan pendekatan kuantitatif komparatif.']],
            ],
            [
                'type' => 'heading',
                'attrs' => ['level' => 3],
                'content' => [['type' => 'text', 'text' => '3.1.1 Tahapan Eksperimen']],
            ],
            [
                'type' => 'bulletList',
                'content' => [
                    [
                        'type' => 'listItem',
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pengumpulan dataset skripsi']]]],
                    ],
                    [
                        'type' => 'listItem',
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Preprocessing dan ekstraksi fitur']]]],
                    ],
                ],
            ],
            [
                'type' => 'orderedList',
                'content' => [
                    [
                        'type' => 'listItem',
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Langkah evaluasi pertama']]]],
                    ],
                    [
                        'type' => 'listItem',
                        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Langkah evaluasi kedua']]]],
                    ],
                ],
            ],
            [
                'type' => 'table',
                'content' => [
                    [
                        'type' => 'tableRow',
                        'content' => [
                            ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Parameter']]]]],
                            ['type' => 'tableHeader', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Nilai Uji']]]]],
                        ],
                    ],
                    [
                        'type' => 'tableRow',
                        'content' => [
                            ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Learning Rate']]]]],
                            ['type' => 'tableCell', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '0.001']]]]],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'figureCaption',
                'content' => [['type' => 'text', 'text' => 'Tabel 3.1: Konfigurasi Hiperparameter Model']],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => $academicDocument,
        ]);

    $response->assertOk();

    // Verify loading project via show endpoint passes chapter content to Inertia
    $showResponse = $this->actingAs($user)->get("/projects/{$project->id}");
    $showResponse->assertOk();
    $showResponse->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->where('chapters.0.content', $academicDocument)
    );
});

test('section auto-saves independently from parent chapter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $parentChapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Parent content']]]]],
    ]);

    $section = Chapter::factory()->create([
        'project_id' => $project->id,
        'parent_id' => $parentChapter->id,
        'title' => '1.1 Latar Belakang Masalah',
        'content' => null,
    ]);

    $sectionContent = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Section specific content']]]],
    ];

    $response = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$section->id}", [
            'content' => $sectionContent,
        ]);

    $response->assertOk();

    expect($section->fresh()->content)->toBe($sectionContent);
    expect($parentChapter->fresh()->content)->toBe([
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Parent content']]]],
    ]);
});
