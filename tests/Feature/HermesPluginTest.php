<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

test('guest cannot query hermes agent', function () {
    $project = Project::factory()->create();

    $response = $this->postJson(route('projects.hermes.chat', $project), [
        'message' => 'Halo Hermes',
    ]);

    $response->assertUnauthorized();
});

test('unauthorized user cannot query hermes on foreign project', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->postJson(route('projects.hermes.chat', $project), [
        'message' => 'Halo Hermes',
    ]);

    $response->assertForbidden();
});

test('validation requires message field', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('projects.hermes.chat', $project), [
        'message' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);
});

test('authorized user successfully receives response from hermes agent', function () {
    Process::fake([
        '*' => Process::result(output: "Hermes: Rumusan masalah Anda sudah tajam."),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => ['type' => 'doc', 'content' => []],
        'order' => 1,
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.hermes.chat', $project), [
        'message' => 'Bagaimana rumusan masalah bab ini?',
        'chapter_id' => $chapter->id,
        'chapter_text' => 'Teks draf latar belakang skripsi...',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['reply', 'created_at'])
        ->assertJsonPath('reply', 'Hermes: Rumusan masalah Anda sudah tajam.');
});

test('handles hermes process execution failure gracefully', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Process crashed with segmentation fault',
            exitCode: 1
        ),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('projects.hermes.chat', $project), [
        'message' => 'Halo',
    ]);

    $response->assertStatus(500)
        ->assertJsonStructure(['error']);
});

test('hermes bridge builds valid command array avoiding batch file ampersand pitfall', function () {
    $bridge = new \App\Services\Ai\HermesBridge();
    $cmd = $bridge->buildCommand('Review R&D & Deep Learning');

    expect($cmd)->toBeArray()
        ->and($cmd)->toContain('-z')
        ->and($cmd)->toContain('Review R&D & Deep Learning');
});

