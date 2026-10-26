<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\SupervisionNote;
use App\Models\SupervisionTask;
use App\Models\User;

test('student can list supervision notes and tasks for project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab I']);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-25',
        'advisor_name' => 'Dr. Budi',
        'notes' => 'Catatan bimbingan awal',
    ]);

    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'chapter_id' => $chapter->id,
        'title' => 'Perbaiki latar belakang',
        'is_completed' => false,
    ]);

    $response = $this->actingAs($user)->getJson(route('projects.supervision-notes.index', $project));
    $response->assertOk();
    $response->assertJsonCount(1, 'supervision_notes');
    $response->assertJsonPath('supervision_notes.0.advisor_name', 'Dr. Budi');
    $response->assertJsonPath('supervision_notes.0.tasks.0.title', 'Perbaiki latar belakang');
    $response->assertJsonPath('supervision_notes.0.tasks.0.chapter_id', $chapter->id);
});

test('student can create supervision note with meeting date, advisor name, and discussion notes', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab II']);

    $payload = [
        'meeting_date' => '2026-10-26',
        'advisor_name' => 'Prof. Dr. Siti',
        'discussion_notes' => 'Fokus pada kajian pustaka teori RRF',
        'tasks' => [
            [
                'title' => 'Tambahkan paper Cormack et al 2009',
                'chapter_id' => $chapter->id,
            ],
            [
                'title' => 'Rangkum definisi RRF',
                'chapter_id' => null,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson(route('projects.supervision-notes.store', $project), $payload);
    $response->assertCreated();

    $this->assertDatabaseHas('supervision_notes', [
        'project_id' => $project->id,
        'advisor_name' => 'Prof. Dr. Siti',
        'meeting_date' => '2026-10-26',
        'notes' => 'Fokus pada kajian pustaka teori RRF',
    ]);

    $this->assertDatabaseHas('supervision_tasks', [
        'project_id' => $project->id,
        'title' => 'Tambahkan paper Cormack et al 2009',
        'chapter_id' => $chapter->id,
        'is_completed' => false,
    ]);

    $this->assertDatabaseHas('supervision_tasks', [
        'project_id' => $project->id,
        'title' => 'Rangkum definisi RRF',
        'chapter_id' => null,
        'is_completed' => false,
    ]);
});

test('student can update supervision note', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-20',
        'advisor_name' => 'Dr. Hartono',
        'notes' => 'Catatan awal',
    ]);

    $response = $this->actingAs($user)->patchJson(route('projects.supervision-notes.update', [$project, $note]), [
        'meeting_date' => '2026-10-21',
        'advisor_name' => 'Dr. Hartono, M.T.',
        'discussion_notes' => 'Catatan diperbarui setelah konsultasi lanjutan',
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('supervision_notes', [
        'id' => $note->id,
        'meeting_date' => '2026-10-21',
        'advisor_name' => 'Dr. Hartono, M.T.',
        'notes' => 'Catatan diperbarui setelah konsultasi lanjutan',
    ]);
});

test('student can delete supervision note and cascade its tasks', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-20',
        'advisor_name' => 'Dr. Hartono',
        'notes' => 'Catatan akan dihapus',
    ]);

    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'title' => 'Tugas terhapus',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('projects.supervision-notes.destroy', [$project, $note]));
    $response->assertOk();

    $this->assertDatabaseMissing('supervision_notes', ['id' => $note->id]);
    $this->assertDatabaseMissing('supervision_tasks', ['id' => $task->id]);
});

test('student can add actionable checklist task to an existing meeting log', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab III Metodologi']);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-22',
        'advisor_name' => 'Dr. Indah',
        'notes' => 'Metodologi penelitian',
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.supervision-notes.tasks.store', [$project, $note]), [
        'title' => 'Tambahkan diagram alir sistem',
        'chapter_id' => $chapter->id,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('supervision_tasks', [
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'chapter_id' => $chapter->id,
        'title' => 'Tambahkan diagram alir sistem',
        'is_completed' => false,
    ]);
});

test('linking a checklist task to a chapter from another project is rejected', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['user_id' => $user->id]);
    $projectB = Project::factory()->create(['user_id' => $user->id]);

    $chapterB = Chapter::factory()->create(['project_id' => $projectB->id, 'title' => 'Bab I Project B']);

    $noteA = SupervisionNote::create([
        'project_id' => $projectA->id,
        'meeting_date' => '2026-10-22',
        'advisor_name' => 'Dr. Indah',
        'notes' => 'Metodologi',
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.supervision-notes.tasks.store', [$projectA, $noteA]), [
        'title' => 'Task dengan foreign chapter',
        'chapter_id' => $chapterB->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['chapter_id']);
});

test('linking a checklist task with non-existent or zero chapter_id is rejected with 422', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-22',
        'advisor_name' => 'Dr. Indah',
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.supervision-notes.tasks.store', [$project, $note]), [
        'title' => 'Task dengan id bab nol',
        'chapter_id' => 0,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['chapter_id']);
});

test('toggling a task updates its completion status in real time', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id]);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-23',
        'advisor_name' => 'Dr. Joko',
    ]);

    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'chapter_id' => $chapter->id,
        'title' => 'Perbaiki tabel 3.1',
        'is_completed' => false,
    ]);

    // First toggle: completes the task
    $response = $this->actingAs($user)->postJson(route('projects.supervision-tasks.toggle', [$project, $task]));
    $response->assertOk();
    $response->assertJsonPath('is_completed', true);

    $task->refresh();
    expect($task->is_completed)->toBeTrue();
    expect($task->completed_at)->not->toBeNull();

    // Second toggle: marks it pending again
    $response2 = $this->actingAs($user)->postJson(route('projects.supervision-tasks.toggle', [$project, $task]));
    $response2->assertOk();
    $response2->assertJsonPath('is_completed', false);

    $task->refresh();
    expect($task->is_completed)->toBeFalse();
    expect($task->completed_at)->toBeNull();
});

test('student can update task title, chapter link, or delete task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter1 = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab I']);
    $chapter2 = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab II']);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-23',
        'advisor_name' => 'Dr. Joko',
    ]);

    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'chapter_id' => $chapter1->id,
        'title' => 'Judul lama',
        'is_completed' => false,
    ]);

    // Update title and move to Chapter 2
    $response = $this->actingAs($user)->patchJson(route('projects.supervision-tasks.update', [$project, $task]), [
        'title' => 'Judul baru',
        'chapter_id' => $chapter2->id,
    ]);
    $response->assertOk();

    $task->refresh();
    expect($task->title)->toBe('Judul baru');
    expect($task->chapter_id)->toBe($chapter2->id);

    // Delete task
    $delResponse = $this->actingAs($user)->deleteJson(route('projects.supervision-tasks.destroy', [$project, $task]));
    $delResponse->assertOk();

    $this->assertDatabaseMissing('supervision_tasks', ['id' => $task->id]);
});

test('project-scoped authorization prevents unauthorized access', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-24',
        'advisor_name' => 'Dr. Budi',
        'notes' => 'Pribadi',
    ]);
    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'title' => 'Tugas rahasia',
    ]);

    // Stranger cannot list
    $this->actingAs($stranger)->getJson(route('projects.supervision-notes.index', $project))
        ->assertForbidden();

    // Stranger cannot create
    $this->actingAs($stranger)->postJson(route('projects.supervision-notes.store', $project), [
        'meeting_date' => '2026-10-25',
        'advisor_name' => 'Hacker',
    ])->assertForbidden();

    // Stranger cannot toggle
    $this->actingAs($stranger)->postJson(route('projects.supervision-tasks.toggle', [$project, $task]))
        ->assertForbidden();

    // Stranger cannot delete note
    $this->actingAs($stranger)->deleteJson(route('projects.supervision-notes.destroy', [$project, $note]))
        ->assertForbidden();

    // Stranger cannot delete task
    $this->actingAs($stranger)->deleteJson(route('projects.supervision-tasks.destroy', [$project, $task]))
        ->assertForbidden();
});

test('project show view passes supervisionNotes prop with tasks and linked chapters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab I Pendahuluan']);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-25',
        'advisor_name' => 'Prof. Dr. Ir. Slamet',
        'notes' => 'Catatan bimbingan untuk props',
    ]);

    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'chapter_id' => $chapter->id,
        'title' => 'Perbaiki batasan masalah',
        'is_completed' => false,
    ]);

    $response = $this->actingAs($user)->get(route('projects.show', $project));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('supervisionNotes', 1)
        ->where('supervisionNotes.0.id', $note->id)
        ->where('supervisionNotes.0.advisor_name', 'Prof. Dr. Ir. Slamet')
        ->has('supervisionNotes.0.tasks', 1)
        ->where('supervisionNotes.0.tasks.0.title', 'Perbaiki batasan masalah')
        ->where('supervisionNotes.0.tasks.0.chapter.id', $chapter->id)
    );
});

test('chapter deletion sets chapter_id in linked supervision task to null', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::factory()->create(['project_id' => $project->id, 'title' => 'Bab Sementara']);

    $note = SupervisionNote::create([
        'project_id' => $project->id,
        'meeting_date' => '2026-10-25',
        'advisor_name' => 'Dr. Budi',
    ]);

    $task = SupervisionTask::create([
        'project_id' => $project->id,
        'supervision_note_id' => $note->id,
        'chapter_id' => $chapter->id,
        'title' => 'Tugas pada bab yang akan dihapus',
    ]);

    expect($chapter->supervisionTasks()->count())->toBe(1);
    expect($chapter->pendingSupervisionTasks()->count())->toBe(1);

    // Delete chapter
    $chapter->delete();

    $task->refresh();
    expect($task->chapter_id)->toBeNull();
    $this->assertDatabaseHas('supervision_tasks', [
        'id' => $task->id,
        'chapter_id' => null,
    ]);
});
