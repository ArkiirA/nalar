<?php

use App\Models\Chapter;
use App\Models\ChapterRevision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('authorized user can list chapter revisions', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => ['type' => 'doc', 'content' => []],
        'order' => 1,
    ]);

    ChapterRevision::create([
        'chapter_id' => $chapter->id,
        'type' => 'manual',
        'title' => 'Versi Awal',
        'content' => ['type' => 'doc', 'content' => []],
        'word_count' => 10,
    ]);

    $response = $this->actingAs($user)->getJson(
        route('projects.chapters.revisions.index', [$project, $chapter])
    );

    $response->assertOk()
        ->assertJsonCount(1, 'revisions')
        ->assertJsonPath('revisions.0.title', 'Versi Awal');
});

test('unauthorized user cannot list or access chapter revisions', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'order' => 1,
    ]);

    $response = $this->actingAs($otherUser)->getJson(
        route('projects.chapters.revisions.index', [$project, $chapter])
    );

    $response->assertForbidden();
});

test('creates auto snapshot when content changes and interval allows', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'order' => 1,
    ]);

    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Latar belakang penelitian ini adalah...']],
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson(
        route('projects.chapters.revisions.auto', [$project, $chapter]),
        ['content' => $content]
    );

    $response->assertCreated();
    expect($chapter->revisions()->count())->toBe(1);
    expect($chapter->revisions()->first()->type)->toBe('auto');
    expect($chapter->revisions()->first()->word_count)->toBeGreaterThan(0);
});

test('skips auto snapshot if less than 5 minutes elapsed', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'order' => 1,
    ]);

    // Create an auto snapshot 2 minutes ago
    ChapterRevision::create([
        'chapter_id' => $chapter->id,
        'type' => 'auto',
        'content' => ['type' => 'doc', 'content' => []],
        'word_count' => 5,
        'created_at' => now()->subMinutes(2),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('projects.chapters.revisions.auto', [$project, $chapter]),
        ['content' => ['type' => 'doc', 'content' => [['type' => 'paragraph']]]]
    );

    $response->assertOk()
        ->assertJsonPath('skipped', true);

    expect($chapter->revisions()->where('type', 'auto')->count())->toBe(1);
});

test('caps auto snapshots at 50 using FIFO rotation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'order' => 1,
    ]);

    // Seed 50 historical auto snapshots (500 minutes ago to 10 minutes ago)
    for ($i = 50; $i >= 1; $i--) {
        DB::table('chapter_revisions')->insert([
            'chapter_id' => $chapter->id,
            'type' => 'auto',
            'content' => json_encode(['type' => 'doc', 'version' => $i]),
            'word_count' => $i,
            'created_at' => now()->subMinutes($i * 10),
            'updated_at' => now()->subMinutes($i * 10),
        ]);
    }

    expect($chapter->revisions()->where('type', 'auto')->count())->toBe(50);

    // Create 51st snapshot
    $newContent = ['type' => 'doc', 'version' => 'newest'];
    $response = $this->actingAs($user)->postJson(
        route('projects.chapters.revisions.auto', [$project, $chapter]),
        ['content' => $newContent]
    );

    $response->assertCreated();
    expect($chapter->revisions()->where('type', 'auto')->count())->toBe(50);
});

test('manual checkpoints are exempt from 50 cap rotation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'order' => 1,
    ]);

    // Create 3 manual checkpoints
    for ($m = 1; $m <= 3; $m++) {
        ChapterRevision::create([
            'chapter_id' => $chapter->id,
            'title' => "Manual Checkpoint {$m}",
            'type' => 'manual',
            'content' => ['type' => 'doc', 'm' => $m],
            'created_at' => now()->subDays($m),
        ]);
    }

    // Seed 50 auto snapshots (500 minutes ago to 10 minutes ago)
    for ($i = 50; $i >= 1; $i--) {
        DB::table('chapter_revisions')->insert([
            'chapter_id' => $chapter->id,
            'type' => 'auto',
            'content' => json_encode(['type' => 'doc', 'version' => $i]),
            'word_count' => $i,
            'created_at' => now()->subMinutes($i * 10),
            'updated_at' => now()->subMinutes($i * 10),
        ]);
    }

    // Trigger another auto snapshot
    $this->actingAs($user)->postJson(
        route('projects.chapters.revisions.auto', [$project, $chapter]),
        ['content' => ['type' => 'doc', 'version' => 'overflow']]
    );

    // Auto snapshots remain 50, manual checkpoints remain 3 (total 53)
    expect($chapter->revisions()->where('type', 'auto')->count())->toBe(50);
    expect($chapter->revisions()->where('type', 'manual')->count())->toBe(3);
    expect($chapter->revisions()->count())->toBe(53);
});

test('can create manual named checkpoint', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'content' => ['type' => 'doc', 'text' => 'konten bab'],
        'order' => 1,
    ]);

    $response = $this->actingAs($user)->postJson(
        route('projects.chapters.revisions.checkpoint', [$project, $chapter]),
        ['title' => 'Draf Siap Bimbingan Dosen']
    );

    $response->assertCreated()
        ->assertJsonPath('revision.title', 'Draf Siap Bimbingan Dosen')
        ->assertJsonPath('revision.type', 'manual');

    expect($chapter->revisions()->where('type', 'manual')->count())->toBe(1);
});

test('can restore chapter to previous revision with safety snapshot', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'content' => ['type' => 'doc', 'version' => 'current_draft'],
        'order' => 1,
    ]);

    $oldRevision = ChapterRevision::create([
        'chapter_id' => $chapter->id,
        'title' => 'Versi Kemarin',
        'type' => 'manual',
        'content' => ['type' => 'doc', 'version' => 'yesterday'],
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->postJson(
        route('projects.chapters.revisions.restore', [$project, $chapter, $oldRevision])
    );

    $response->assertOk();

    // Chapter content is restored to old revision
    $chapter->refresh();
    expect($chapter->content)->toBe(['type' => 'doc', 'version' => 'yesterday']);

    // Safety snapshot was created
    $safetySnapshot = $chapter->revisions()->where('title', 'like', 'Sebelum pemulihan ke%')->first();
    expect($safetySnapshot)->not->toBeNull();
    expect($safetySnapshot->content)->toBe(['type' => 'doc', 'version' => 'current_draft']);
});

test('cascades delete revisions when chapter or project is deleted', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I',
        'order' => 1,
    ]);

    $revision = ChapterRevision::create([
        'chapter_id' => $chapter->id,
        'type' => 'auto',
        'content' => ['type' => 'doc'],
    ]);

    expect(ChapterRevision::find($revision->id))->not->toBeNull();

    $project->delete();

    expect(ChapterRevision::find($revision->id))->toBeNull();
});
