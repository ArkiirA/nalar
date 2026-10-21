<?php

use App\Models\Chapter;
use App\Models\ChapterRevision;
use App\Models\Project;
use App\Models\User;

test('automatic background snapshot is captured on first active edit', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => null,
    ]);

    expect($chapter->revisions()->count())->toBe(0);

    $contentV1 = [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Kalimat pertama bab 1.']]],
        ],
    ];

    $response = $this->actingAs($user)
        ->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
            'content' => $contentV1,
        ]);

    $response->assertOk();

    expect($chapter->revisions()->count())->toBe(1);
    $snapshot = $chapter->revisions()->first();
    expect($snapshot->title)->toBe('Snapshot Otomatis');
    expect($snapshot->is_manual)->toBeFalse();
    expect($snapshot->content)->toBe($contentV1);
});

test('automatic background snapshot obeys 5-minute frequency rule during active edits', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
    ]);

    $content1 = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks menit ke-0']]]],
    ];

    // T = 0: First edit creates snapshot #1
    $this->actingAs($user)->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
        'content' => $content1,
    ])->assertOk();

    expect($chapter->revisions()->count())->toBe(1);

    // T = 2 minutes: active typing within 5-minute window should NOT record a new snapshot
    $this->travel(2)->minutes();

    $content2 = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks menit ke-2']]]],
    ];

    $this->actingAs($user)->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
        'content' => $content2,
    ])->assertOk();

    expect($chapter->revisions()->count())->toBe(1);
    expect($chapter->fresh()->content)->toBe($content2);

    // T = 4 minutes: still within 5-minute window from snapshot #1
    $this->travel(2)->minutes();

    $content3 = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks menit ke-4']]]],
    ];

    $this->actingAs($user)->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
        'content' => $content3,
    ])->assertOk();

    expect($chapter->revisions()->count())->toBe(1);

    // T = 6 minutes: past the 5-minute interval, active edits trigger snapshot #2
    $this->travel(2)->minutes();

    $content4 = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Teks menit ke-6']]]],
    ];

    $this->actingAs($user)->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
        'content' => $content4,
    ])->assertOk();

    expect($chapter->revisions()->count())->toBe(2);
    $latestSnapshot = $chapter->revisions()->first();
    expect($latestSnapshot->content)->toBe($content4);
});

test('auto snapshot does not duplicate unchanged content after 5 minutes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);

    $content = [
        'type' => 'doc',
        'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten konstan']]]],
    ];

    $this->actingAs($user)->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
        'content' => $content,
    ])->assertOk();

    expect($chapter->revisions()->count())->toBe(1);

    // Travel 10 minutes, but payload has unchanged content
    $this->travel(10)->minutes();

    $this->actingAs($user)->patchJson("/projects/{$project->id}/chapters/{$chapter->id}", [
        'content' => $content,
    ])->assertOk();

    expect($chapter->revisions()->count())->toBe(1);
});

test('snapshot cap limit strictly enforces maximum 50 snapshots by pruning oldest', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);

    // Create 50 initial snapshots
    for ($i = 1; $i <= 50; $i++) {
        $chapter->revisions()->create([
            'title' => "Snapshot {$i}",
            'content' => ['iteration' => $i],
            'is_manual' => false,
            'created_at' => now()->subMinutes(100 - $i),
        ]);
    }

    expect($chapter->revisions()->count())->toBe(50);
    $oldestOriginal = ChapterRevision::where('chapter_id', $chapter->id)->orderBy('created_at', 'asc')->orderBy('id', 'asc')->first();
    $oldestOriginalId = $oldestOriginal->id;
    expect($oldestOriginal->title)->toBe('Snapshot 1');

    // Create 51st snapshot via recordSnapshot
    $newSnapshot = $chapter->recordSnapshot('Snapshot 51', true, ['iteration' => 51]);

    // Total must stay capped at 50
    expect($chapter->revisions()->count())->toBe(50);

    // Oldest original snapshot must have been pruned
    expect(ChapterRevision::find($oldestOriginalId))->toBeNull();

    // Newest snapshot must be present
    expect(ChapterRevision::find($newSnapshot->id))->not->toBeNull();
});

test('snapshot cap limit protects manual checkpoints when pruning auto snapshots', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);

    // Create an old manual checkpoint
    $manualCheckpoint = $chapter->revisions()->create([
        'title' => 'Manual Milestone Checkpoint',
        'content' => ['milestone' => 1],
        'is_manual' => true,
        'created_at' => now()->subDays(10),
    ]);

    // Create 49 auto snapshots
    for ($i = 1; $i <= 49; $i++) {
        $chapter->revisions()->create([
            'title' => "Auto Snapshot {$i}",
            'content' => ['auto' => $i],
            'is_manual' => false,
            'created_at' => now()->subMinutes(60 - $i),
        ]);
    }

    expect($chapter->revisions()->count())->toBe(50);

    // 51st snapshot is an auto snapshot
    $chapter->recordSnapshot('Auto Snapshot 50', false, ['auto' => 50]);

    expect($chapter->revisions()->count())->toBe(50);
    // Old manual checkpoint is preserved
    expect(ChapterRevision::find($manualCheckpoint->id))->not->toBeNull();
    // Oldest auto snapshot was pruned instead
    expect(ChapterRevision::where('title', 'Auto Snapshot 1')->first())->toBeNull();
});

test('student can create a named manual snapshot checkpoint at any time and avoids immediate duplicates', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'content' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Draft siap bimbingan.']]]],
        ],
    ]);

    // Create an auto snapshot 1 hour ago
    $chapter->revisions()->create([
        'title' => 'Snapshot Otomatis',
        'content' => ['old' => 'data'],
        'is_manual' => false,
        'created_at' => now()->subHours(1),
    ]);
    expect($chapter->revisions()->count())->toBe(1);

    // Student creates a named manual checkpoint
    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions", [
        'title' => 'Draft Revisi Dosen Pembimbing I',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('revision.title', 'Draft Revisi Dosen Pembimbing I');
    $response->assertJsonPath('revision.is_manual', true);

    expect($chapter->revisions()->count())->toBe(2);

    $latest = $chapter->revisions()->first();
    expect($latest->title)->toBe('Draft Revisi Dosen Pembimbing I');
    expect($latest->is_manual)->toBeTrue();
    expect($latest->content)->toBe($chapter->content);
});

test('manual checkpoint upgrades recent identical auto snapshot to avoid duplicate entries', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'content' => [
            'type' => 'doc',
            'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Konten sama']]]],
        ],
    ]);

    // Recent auto-save snapshot (created 2 seconds ago with same content)
    $chapter->recordSnapshot('Snapshot Otomatis', false);
    expect($chapter->revisions()->count())->toBe(1);

    // Immediate manual checkpoint
    $response = $this->actingAs($user)->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions", [
        'title' => 'Checkpoint Versi Bersih',
    ]);

    $response->assertCreated();
    expect($chapter->revisions()->count())->toBe(1);
    expect($chapter->revisions()->first()->title)->toBe('Checkpoint Versi Bersih');
    expect($chapter->revisions()->first()->is_manual)->toBeTrue();
});

test('revision listing returns chronological snapshots with relative timestamps and single endpoint loads content', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);

    $rev1 = $chapter->revisions()->create([
        'title' => 'Snapshot Pertama',
        'content' => ['v' => 1],
        'is_manual' => false,
        'created_at' => now()->subHours(2),
    ]);

    $rev2 = $chapter->revisions()->create([
        'title' => 'Draft Manual Kedua',
        'content' => ['v' => 2],
        'is_manual' => true,
        'created_at' => now()->subMinutes(10),
    ]);

    $response = $this->actingAs($user)->getJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions");

    $response->assertOk();
    $revisions = $response->json('revisions');

    expect($revisions)->toHaveCount(2);
    expect($revisions[0]['id'])->toBe($rev2->id);
    expect($revisions[0]['title'])->toBe('Draft Manual Kedua');
    expect($revisions[0]['is_manual'])->toBeTrue();
    expect($revisions[0]['relative_time'])->toBeString();
    expect(array_key_exists('content', $revisions[0]))->toBeFalse();

    expect($revisions[1]['id'])->toBe($rev1->id);
    expect($revisions[1]['title'])->toBe('Snapshot Pertama');
    expect($revisions[1]['is_manual'])->toBeFalse();

    // Show endpoint loads full structured content
    $showResponse = $this->actingAs($user)->getJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$rev2->id}");
    $showResponse->assertOk();
    $showResponse->assertJsonPath('revision.content.v', 2);
});

test('student can restore chapter to a previous snapshot with full content integrity', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $initialContent = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'heading',
                'attrs' => ['level' => 1],
                'content' => [['type' => 'text', 'text' => 'BAB I PENDAHULUAN']],
            ],
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Versi lama yang ingin dipulihkan.']],
            ],
            [
                'type' => 'figureCaption',
                'attrs' => ['captionType' => 'figure', 'number' => '1.1'],
                'content' => [['type' => 'text', 'text' => 'Gambar 1.1 Diagram Alir Penelitian']],
            ],
        ],
    ];

    $chapter = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => $initialContent,
    ]);

    // Save snapshot of initial version
    $snapshotToRestore = $chapter->recordSnapshot('Snapshot Bagus', true, $initialContent);

    // User subsequently makes unwanted changes
    $damagedContent = [
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Perubahan yang tidak diinginkan dan salah hapus.']]],
        ],
    ];
    $chapter->update(['content' => $damagedContent]);
    expect($chapter->fresh()->content)->toBe($damagedContent);

    // Restore the previous snapshot
    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$snapshotToRestore->id}/restore");

    $response->assertOk();
    $response->assertJsonPath('chapter.content', $initialContent);

    // Verify DB integrity
    $chapter->refresh();
    expect($chapter->content)->toBe($initialContent);
});

test('chapter revisions enforce tenant isolation and project boundary', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);
    $revision = $chapter->recordSnapshot('Owner Snapshot', true, ['doc' => 1]);

    // Attacker cannot view revisions
    $this->actingAs($attacker)
        ->getJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions")
        ->assertForbidden();

    // Attacker cannot create revision
    $this->actingAs($attacker)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions", ['title' => 'Attacker'])
        ->assertForbidden();

    // Attacker cannot restore revision
    $this->actingAs($attacker)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$revision->id}/restore")
        ->assertForbidden();

    // Mismatched project ID returns 404
    $otherProject = Project::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)
        ->getJson("/projects/{$otherProject->id}/chapters/{$chapter->id}/revisions")
        ->assertNotFound();

    // Mismatched chapter and revision ID returns 404
    $otherChapter = Chapter::factory()->create(['project_id' => $project->id]);
    $otherRevision = $otherChapter->recordSnapshot('Other', true);

    $this->actingAs($owner)
        ->getJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$otherRevision->id}")
        ->assertNotFound();

    $this->actingAs($owner)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions/{$otherRevision->id}/restore")
        ->assertNotFound();
});

test('manual checkpoint creation validates required title', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->postJson("/projects/{$project->id}/chapters/{$chapter->id}/revisions", [
            'title' => '',
        ])
        ->assertJsonValidationErrors(['title']);
});
