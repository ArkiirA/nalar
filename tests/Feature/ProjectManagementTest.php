<?php

use App\Models\Project;
use App\Models\User;

test('authenticated user can create a thesis project with title and target completion date', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/projects', [
        'title' => 'Sistem Deteksi Anomali Jaringan Berbasis Graf',
        'target_completion_date' => '2026-12-31',
    ]);

    $response->assertRedirect('/projects');

    $this->assertDatabaseHas('projects', [
        'user_id' => $user->id,
        'title' => 'Sistem Deteksi Anomali Jaringan Berbasis Graf',
    ]);

    $project = Project::where('title', 'Sistem Deteksi Anomali Jaringan Berbasis Graf')->firstOrFail();
    expect($project->target_completion_date?->format('Y-m-d'))->toBe('2026-12-31');
});

test('project creation requires a title', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/projects', [
        'title' => '',
        'target_completion_date' => '2026-12-31',
    ]);

    $response->assertSessionHasErrors(['title']);
});

test('authenticated user can view their list of projects', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $userProject = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Thesis Project',
    ]);

    $otherProject = Project::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other User Project',
    ]);

    $response = $this->actingAs($user)->get('/projects');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Index')
        ->has('projects', 1)
        ->where('projects.0.id', $userProject->id)
        ->where('projects.0.title', 'My Thesis Project')
    );
});

test('authenticated user can update their project details', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Title',
        'target_completion_date' => '2026-10-01',
    ]);

    $response = $this->actingAs($user)->patch("/projects/{$project->id}", [
        'title' => 'Updated Thesis Title',
        'target_completion_date' => '2027-01-15',
    ]);

    $response->assertRedirect('/projects');

    $project->refresh();
    expect($project->title)->toBe('Updated Thesis Title');
    expect($project->target_completion_date?->format('Y-m-d'))->toBe('2027-01-15');
});
