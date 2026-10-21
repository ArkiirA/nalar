<?php

use App\Models\Document;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('resolving a doi imports standard bibliographic metadata via content negotiation', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $fakeDoi = '10.1016/j.future.2020.08.012';

    Http::fake([
        'https://doi.org/*' => Http::response([
            'type' => 'article-journal',
            'title' => 'Cloud and Fog Computing Orchestration Frameworks',
            'author' => [
                ['family' => 'Perez', 'given' => 'Maria'],
                ['family' => 'Garcia', 'given' => 'Jose'],
            ],
            'issued' => [
                'date-parts' => [[2021]],
            ],
            'container-title' => 'Future Generation Computer Systems',
            'volume' => '115',
            'issue' => '3',
            'page' => '210-225',
            'DOI' => $fakeDoi,
        ], 200),
    ]);

    $response = $this->actingAs($user)
        ->postJson("/projects/{$project->id}/references/resolve-doi", [
            'doi' => "https://doi.org/{$fakeDoi}",
        ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('metadata.title', 'Cloud and Fog Computing Orchestration Frameworks');
    $response->assertJsonPath('metadata.year', 2021);
    $response->assertJsonPath('metadata.publication_title', 'Future Generation Computer Systems');
    $response->assertJsonPath('metadata.volume', '115');
    $response->assertJsonPath('metadata.issue', '3');
    $response->assertJsonPath('metadata.pages', '210-225');
    $response->assertJsonPath('metadata.authors.0.family', 'Perez');
});

test('user can manually create a reference and see missing field warnings', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Incomplete journal reference missing volume and pages
    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/references", [
            'type' => 'article-journal',
            'title' => 'Studi Kasus Natural Language Processing',
            'authors' => [
                ['family' => 'Kurniawan', 'given' => 'Ahmad'],
            ],
            'year' => 2023,
            'publication_title' => 'Jurnal Teknologi Cerdas',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('references', [
        'project_id' => $project->id,
        'title' => 'Studi Kasus Natural Language Processing',
        'type' => 'article-journal',
        'year' => 2023,
    ]);

    $reference = Reference::where('project_id', $project->id)->first();
    $warnings = $reference->getMissingFieldWarnings();

    expect($warnings)->toHaveKey('volume');
    expect($warnings)->toHaveKey('pages');
});

test('system detects duplicate reference doi within the same project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Reference::factory()->create([
        'project_id' => $project->id,
        'doi' => '10.1234/skripsi.2023.01',
        'title' => 'Paper Pertama',
    ]);

    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/references", [
            'type' => 'article-journal',
            'title' => 'Paper Berbeda Tapi DOI Sama',
            'doi' => 'https://doi.org/10.1234/skripsi.2023.01',
        ]);

    $response->assertSessionHasErrors('doi');
    expect($project->references()->count())->toBe(1);
});

test('system detects duplicate reference title within the same project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Metodologi Penelitian Terapan untuk Skripsi',
    ]);

    // Submitting with slightly different casing and whitespace
    $response = $this->actingAs($user)
        ->post("/projects/{$project->id}/references", [
            'type' => 'book',
            'title' => '  metodologi penelitian terapan untuk skripsi  ',
        ]);

    $response->assertSessionHasErrors('title');
    expect($project->references()->count())->toBe(1);
});

test('different projects can register identical reference title and doi without conflict', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $project1 = Project::factory()->create(['user_id' => $user1->id]);
    $project2 = Project::factory()->create(['user_id' => $user2->id]);

    Reference::factory()->create([
        'project_id' => $project1->id,
        'doi' => '10.1234/shared.doi',
        'title' => 'Shared Reference Title',
    ]);

    $response = $this->actingAs($user2)
        ->post("/projects/{$project2->id}/references", [
            'type' => 'article-journal',
            'doi' => '10.1234/shared.doi',
            'title' => 'Shared Reference Title',
        ]);

    $response->assertRedirect();
    expect($project2->references()->count())->toBe(1);
});

test('user can update an existing reference and edit its bibliographic fields', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $reference = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Judul Sebelum Revisi',
        'year' => 2020,
    ]);

    $response = $this->actingAs($user)
        ->patch("/projects/{$project->id}/references/{$reference->id}", [
            'type' => 'book',
            'title' => 'Judul Sesudah Revisi',
            'year' => 2022,
            'publisher' => 'Penerbit Salemba Empat',
        ]);

    $response->assertRedirect();

    $reference->refresh();
    expect($reference->title)->toBe('Judul Sesudah Revisi');
    expect($reference->year)->toBe(2022);
    expect($reference->publisher)->toBe('Penerbit Salemba Empat');
});

test('user can delete a reference', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    $reference = Reference::factory()->create(['project_id' => $project->id]);

    $response = $this->actingAs($user)
        ->delete("/projects/{$project->id}/references/{$reference->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('references', ['id' => $reference->id]);
});

test('user cannot update or delete reference belonging to another users project', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    $reference = Reference::factory()->create(['project_id' => $project->id, 'title' => 'Karya Ilmiah Asli']);

    $patchResponse = $this->actingAs($attacker)
        ->patch("/projects/{$project->id}/references/{$reference->id}", [
            'title' => 'Diubah Oleh Attacker',
        ]);
    $patchResponse->assertForbidden();

    $deleteResponse = $this->actingAs($attacker)
        ->delete("/projects/{$project->id}/references/{$reference->id}");
    $deleteResponse->assertForbidden();

    expect($reference->fresh()->title)->toBe('Karya Ilmiah Asli');
});

test('user can switch project citation style between apa and harvard', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'citation_style' => 'apa',
    ]);

    $response = $this->actingAs($user)
        ->patch("/projects/{$project->id}/citation-style", [
            'citation_style' => 'harvard',
        ]);

    $response->assertRedirect();
    expect($project->fresh()->citation_style)->toBe('harvard');
});

test('deleting project cascades and removes all references', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $ref1 = Reference::factory()->create(['project_id' => $project->id]);
    $ref2 = Reference::factory()->create(['project_id' => $project->id]);

    expect($project->references()->count())->toBe(2);

    $this->actingAs($user)->delete("/projects/{$project->id}")->assertRedirect('/projects');

    $this->assertDatabaseMissing('references', ['id' => $ref1->id]);
    $this->assertDatabaseMissing('references', ['id' => $ref2->id]);
});

test('project show includes references prop with formatted bibliographic entries', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'citation_style' => 'apa',
    ]);

    $ref = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Kajian Kecerdasan Buatan',
        'year' => 2024,
    ]);

    $response = $this->actingAs($user)->get("/projects/{$project->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('references', 1)
        ->where('references.0.id', $ref->id)
        ->where('references.0.title', 'Kajian Kecerdasan Buatan')
        ->has('references.0.formatted_apa')
        ->has('references.0.formatted_harvard')
        ->has('references.0.missing_field_warnings')
    );
});

