<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use App\Services\Ai\EvidenceCheckerService;
use App\Services\Ai\HermesBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

test('guest cannot check evidence', function () {
    $project = Project::factory()->create();

    $response = $this->postJson(route('projects.evidence.check', $project), [
        'claim' => 'Algoritma A* optimal.',
    ]);

    $response->assertUnauthorized();
});

test('unauthorized user cannot check evidence on foreign project', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->postJson(route('projects.evidence.check', $project), [
        'claim' => 'Algoritma A* optimal.',
    ]);

    $response->assertForbidden();
});

test('claim field is required', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson(route('projects.evidence.check', $project), [
        'claim' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['claim']);
});

test('evidence checker detects overclaim and returns contradicted status via heuristic fallback', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Force HermesBridge to fail to trigger heuristic fallback
    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'CLI offline', exitCode: 1),
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.evidence.check', $project), [
        'claim' => 'Metode deep learning selalu pasti berhasil tanpa ragu pada semua kasus.',
    ]);

    $response->assertOk()
        ->assertJsonPath('assessment.status', EvidenceCheckerService::STATUS_CONTRADICTED)
        ->assertJsonStructure([
            'claim',
            'assessment' => [
                'status',
                'confidence',
                'analysis',
                'excerpts',
                'recommendation',
                'checked_at',
            ],
        ]);
});

test('evidence checker categorizes unverified claim without context as insufficient evidence', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Process::fake([
        '*' => Process::result(output: '', errorOutput: 'CLI offline', exitCode: 1),
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.evidence.check', $project), [
        'claim' => 'Akurasi model mencapai 99.4% dengan latency 2ms.',
    ]);

    $response->assertOk()
        ->assertJsonPath('assessment.status', EvidenceCheckerService::STATUS_INSUFFICIENT);
});

test('evidence checker parses structured ai json response successfully', function () {
    $mockOutput = json_encode([
        'status' => 'Supported',
        'confidence' => 92,
        'analysis' => 'Klaim didukung oleh teorema Bellman dan literatur optimasi.',
        'excerpts' => ['Cormen et al. (2022), Introduction to Algorithms, p. 650'],
        'recommendation' => 'Algoritma Dijkstra menjamin solusi lintasan terpendek pada graf berbobot non-negatif.',
    ]);

    Process::fake([
        '*' => Process::result(output: $mockOutput),
    ]);

    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $chapter = Chapter::create([
        'project_id' => $project->id,
        'title' => 'BAB II LANDASAN TEORI',
        'content' => ['type' => 'doc', 'content' => []],
        'order' => 2,
    ]);

    $response = $this->actingAs($user)->postJson(route('projects.evidence.check', $project), [
        'claim' => 'Dijkstra menemukan jalur terpendek pada graf dengan bobot non-negatif.',
        'chapter_id' => $chapter->id,
        'context' => 'Dijkstra algorithm ensures optimal shortest path on graphs without negative weights.',
    ]);

    $response->assertOk()
        ->assertJsonPath('assessment.status', EvidenceCheckerService::STATUS_SUPPORTED)
        ->assertJsonPath('assessment.confidence', 92)
        ->assertJsonPath('assessment.analysis', 'Klaim didukung oleh teorema Bellman dan literatur optimasi.')
        ->assertJsonPath('assessment.recommendation', 'Algoritma Dijkstra menjamin solusi lintasan terpendek pada graf berbobot non-negatif.');
});
