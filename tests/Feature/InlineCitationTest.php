<?php

use App\Models\Chapter;
use App\Models\Document;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('chapter auto-save serializes and preserves inline citation nodes with reference_id and label', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => null,
    ]);

    $reference = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Analisis Performa Model Transformer',
        'authors' => [['family' => 'Wijaya', 'given' => 'Budi']],
        'year' => 2023,
    ]);

    $tiptapContent = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Menurut penelitian'],
                    [
                        'type' => 'citation',
                        'attrs' => [
                            'reference_id' => $reference->id,
                            'label' => '(Wijaya, 2023)',
                            'reference_key' => $reference->reference_key,
                        ],
                    ],
                    ['type' => 'text', 'text' => ', model deep learning memberikan akurasi optimal.'],
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
    expect($chapter->content)->toBe($tiptapContent);
    expect(Chapter::extractCitationReferenceIds($chapter->content))->toBe([$reference->id]);

    // Verify Inertia show endpoint serves chapter with citation intact
    $showResponse = $this->actingAs($user)->get("/projects/{$project->id}");
    $showResponse->assertOk();
    $showResponse->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->where('chapters.0.content', $tiptapContent)
    );
});

test('chapter auto-save rejects non-existent reference ids to prevent orphaned citations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB II TINJAUAN PUSTAKA',
        'content' => null,
    ]);

    $invalidPayload = [
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Mengacu pada '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => 999999,
                                'label' => '(Ghost, 2099)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", $invalidPayload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);

    expect($chapter->fresh()->content)->toBeNull();
});

test('chapter auto-save rejects reference ids belonging to another project', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $projectA = Project::factory()->create(['user_id' => $userA->id]);
    $referenceA = Reference::factory()->create([
        'project_id' => $projectA->id,
        'title' => 'Paper Proyek A',
    ]);

    $projectB = Project::factory()->create(['user_id' => $userB->id]);
    $chapterB = Chapter::factory()->create([
        'project_id' => $projectB->id,
        'title' => 'BAB I',
        'content' => null,
    ]);

    $crossTenantPayload = [
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $referenceA->id,
                                'label' => '(Foreign, 2023)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($userB)
        ->patchJson("/projects/{$projectB->id}/chapters/{$chapterB->id}", $crossTenantPayload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['content']);

    expect($chapterB->fresh()->content)->toBeNull();
});

test('deleting a reference is blocked if cited in a chapter to prevent orphaned citations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $reference = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Referensi Aktif Disitasi',
        'authors' => [['family' => 'Pratama', 'given' => 'Andi']],
        'year' => 2022,
    ]);

    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $reference->id,
                                'label' => '(Pratama, 2022)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    expect($reference->isCited())->toBeTrue();

    // Deleting via json endpoint
    $response = $this->actingAs($user)
        ->deleteJson("/projects/{$project->id}/references/{$reference->id}");

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['reference']);

    $this->assertDatabaseHas('references', ['id' => $reference->id]);

    // Deleting via web/inertia form request
    $webResponse = $this->actingAs($user)
        ->delete("/projects/{$project->id}/references/{$reference->id}");

    $webResponse->assertSessionHasErrors(['reference']);
    $this->assertDatabaseHas('references', ['id' => $reference->id]);
});

test('reference can be deleted after citation is removed from chapter', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $reference = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Referensi Siap Dihapus',
    ]);

    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $reference->id,
                                'label' => '(Pratama, 2022)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    // Update chapter to remove citation
    $chapter->update([
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Teks tanpa sitasi apapun.'],
                    ],
                ],
            ],
        ],
    ]);

    expect($reference->isCited())->toBeFalse();

    $response = $this->actingAs($user)
        ->deleteJson("/projects/{$project->id}/references/{$reference->id}");

    $response->assertOk();
    $this->assertDatabaseMissing('references', ['id' => $reference->id]);
});

test('document file endpoint streams physical pdf and enforces tenant isolation', function () {
    Storage::fake('local');

    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);

    $fakePdf = UploadedFile::fake()->create('riset-thesis.pdf', 500, 'application/pdf');
    $storedPath = $fakePdf->store("projects/{$project->id}/documents", 'local');

    $document = Document::factory()->create([
        'project_id' => $project->id,
        'title' => 'Riset Thesis',
        'original_filename' => 'riset-thesis.pdf',
        'file_path' => $storedPath,
        'mime_type' => 'application/pdf',
        'file_size' => 500 * 1024,
    ]);

    // Owner can view / download physical PDF
    $response = $this->actingAs($owner)
        ->get("/projects/{$project->id}/documents/{$document->id}/file");

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');

    // Attacker cannot view PDF
    $unauthResponse = $this->actingAs($attacker)
        ->get("/projects/{$project->id}/documents/{$document->id}/file");

    $unauthResponse->assertForbidden();
});

test('chapter revision snapshot records and restores inline citations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'content' => null,
    ]);

    $reference = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Studi AI',
        'authors' => [['family' => 'Santoso', 'given' => 'Agus']],
        'year' => 2024,
    ]);

    $contentWithCitation = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Berdasarkan temuan'],
                    [
                        'type' => 'citation',
                        'attrs' => [
                            'reference_id' => $reference->id,
                            'label' => '(Santoso, 2024)',
                        ],
                    ],
                ],
            ],
        ],
    ];

    // Auto-save content with citation
    $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => $contentWithCitation,
        ])
        ->assertOk();

    // Create a manual checkpoint revision
    $revResponse = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions", [
            'title' => 'Versi dengan sitasi',
        ]);
    $revResponse->assertCreated();
    $revId = $revResponse->json('revision.id');

    // Overwrite content
    $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks baru tanpa sitasi']]],
                ],
            ],
        ])
        ->assertOk();

    // Restore revision
    $restoreResponse = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$revId}/restore");
    $restoreResponse->assertOk();

    expect($chapter->fresh()->content)->toBe($contentWithCitation);
});

test('chapter revision restore rejects snapshot referencing deleted reference', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'content' => null,
    ]);

    $reference = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Studi AI',
        'authors' => [['family' => 'Santoso', 'given' => 'Agus']],
        'year' => 2024,
    ]);

    $contentWithCitation = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Berdasarkan temuan'],
                    [
                        'type' => 'citation',
                        'attrs' => [
                            'reference_id' => $reference->id,
                            'label' => '(Santoso, 2024)',
                        ],
                    ],
                ],
            ],
        ],
    ];

    // Save and record snapshot
    $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => $contentWithCitation,
        ])
        ->assertOk();

    $revResponse = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions", [
            'title' => 'Versi sebelum hapus',
        ]);
    $revResponse->assertCreated();
    $revId = $revResponse->json('revision.id');

    // Remove citation from chapter content so reference is no longer cited in current document
    $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks bersih']]],
                ],
            ],
        ])
        ->assertOk();

    // Now reference can be safely deleted
    $this->actingAs($user)
        ->deleteJson("/projects/{$project->id}/references/{$reference->id}")
        ->assertOk();

    // Trying to restore the old snapshot that cited the deleted reference must fail
    $restoreResponse = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$revId}/restore");

    $restoreResponse->assertStatus(422);
    $restoreResponse->assertJsonValidationErrors(['revision']);
});
