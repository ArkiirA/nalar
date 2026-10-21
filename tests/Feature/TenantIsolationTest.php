<?php

use App\Models\Project;
use App\Models\User;

test('authenticated user can open their own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->where('project.id', $project->id)
    );
});

test('user cannot view another users project', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($attacker)->get("/projects/{$project->id}");

    $response->assertForbidden();
});

test('user cannot edit or update another users project', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create([
        'user_id' => $owner->id,
        'title' => 'Original Title',
    ]);

    $response = $this->actingAs($attacker)->patch("/projects/{$project->id}", [
        'title' => 'Tampered Title',
    ]);

    $response->assertForbidden();
    expect($project->fresh()->title)->toBe('Original Title');
});

test('user cannot delete another users project', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($attacker)->delete("/projects/{$project->id}");

    $response->assertForbidden();
    $this->assertDatabaseHas('projects', ['id' => $project->id]);
});

test('user can delete their own project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->delete("/projects/{$project->id}");

    $response->assertRedirect('/projects');
    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});
