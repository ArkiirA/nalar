<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use App\Support\GuidelinePresets;

test('guideline presets contain all required campuses with bab 1 through bab 5 outlines', function () {
    $presets = GuidelinePresets::all();

    expect($presets)->toHaveKeys(['universal_s1', 'ui', 'ugm', 'itb', 'gunadarma']);

    foreach (['universal_s1', 'ui', 'ugm', 'itb', 'gunadarma'] as $presetKey) {
        $preset = $presets[$presetKey];
        expect($preset['outline'])->toHaveCount(5);
        expect($preset['outline'][0]['title'])->toContain('PENDAHULUAN');
        expect(count($preset['outline'][0]['sections']))->toBeGreaterThan(0);
        expect($preset['formatting'])->toHaveKeys(['font_family', 'font_size', 'line_spacing', 'margins']);
    }
});

test('creating a project with guideline_preset populates default chapter outline', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/projects', [
        'title' => 'Riset Evaluasi Algoritma Klasifikasi Teks',
        'target_completion_date' => '2026-11-30',
        'guideline_preset' => 'ui',
    ]);

    $response->assertRedirect('/projects');

    $project = Project::where('title', 'Riset Evaluasi Algoritma Klasifikasi Teks')->firstOrFail();
    expect($project->guideline_preset)->toBe('ui');
    expect($project->guideline_profile['university'])->toBe('Universitas Indonesia');

    $chapters = $project->rootChapters()->with('sections')->get();
    expect($chapters)->toHaveCount(5);
    expect($chapters->first()->title)->toBe('BAB 1 PENDAHULUAN');
    expect($chapters->first()->sections)->not->toBeEmpty();
});

test('applying guideline preset attaches preset and creates chapters and sections', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/projects/{$project->id}/guideline-preset", [
        'preset' => 'ugm',
    ]);

    $response->assertRedirect();

    $project->refresh();
    expect($project->guideline_preset)->toBe('ugm');
    expect($project->guideline_profile['university'])->toBe('Universitas Gadjah Mada');

    $chapters = $project->rootChapters()->with('sections')->get();
    expect($chapters)->toHaveCount(5);
    expect($chapters[0]->title)->toBe('BAB I PENDAHULUAN');
    expect($chapters[0]->sections->pluck('title')->all())->toContain('1.1 Latar Belakang');
    expect($chapters[4]->title)->toBe('BAB V KESIMPULAN DAN SARAN');
});

test('student can customize chapter and section titles', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    GuidelinePresets::applyToProject($project, 'universal_s1');

    $chapter = $project->rootChapters()->first();
    $section = $chapter->sections()->first();

    // Customize chapter title
    $response = $this->actingAs($user)->patch("/projects/{$project->id}/chapters/{$chapter->id}", [
        'title' => 'BAB I PENDAHULUAN & LATAR BELAKANG KHUSUS',
    ]);
    $response->assertRedirect();
    expect($chapter->fresh()->title)->toBe('BAB I PENDAHULUAN & LATAR BELAKANG KHUSUS');

    // Customize section title
    $response = $this->actingAs($user)->patch("/projects/{$project->id}/chapters/{$section->id}", [
        'title' => '1.1 Latar Belakang Studi Kasus',
    ]);
    $response->assertRedirect();
    expect($section->fresh()->title)->toBe('1.1 Latar Belakang Studi Kasus');
});

test('student can create new chapters and sections', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // Create a new chapter
    $response = $this->actingAs($user)->post("/projects/{$project->id}/chapters", [
        'title' => 'BAB VI IMPLEMENTASI KHUSUS',
        'order' => 6,
    ]);
    $response->assertRedirect();

    $chapter = Chapter::where('project_id', $project->id)->where('title', 'BAB VI IMPLEMENTASI KHUSUS')->firstOrFail();
    expect($chapter->parent_id)->toBeNull();

    // Create a section under that chapter
    $response = $this->actingAs($user)->post("/projects/{$project->id}/chapters", [
        'parent_id' => $chapter->id,
        'title' => '6.1 Pengujian Performa Model',
        'order' => 1,
    ]);
    $response->assertRedirect();

    $section = Chapter::where('project_id', $project->id)->where('title', '6.1 Pengujian Performa Model')->firstOrFail();
    expect($section->parent_id)->toBe($chapter->id);
});

test('student can reorder chapters and sections in the outline', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    GuidelinePresets::applyToProject($project, 'universal_s1');

    $chapters = $project->rootChapters()->get();
    $chapter1 = $chapters[0];
    $chapter2 = $chapters[1];

    // Swap orders
    $response = $this->actingAs($user)->post("/projects/{$project->id}/chapters/reorder", [
        'items' => [
            ['id' => $chapter1->id, 'order' => 2, 'parent_id' => null],
            ['id' => $chapter2->id, 'order' => 1, 'parent_id' => null],
        ],
    ]);
    $response->assertRedirect();

    expect($chapter1->fresh()->order)->toBe(2);
    expect($chapter2->fresh()->order)->toBe(1);
});

test('student can delete a section or chapter and chapter cascades to sections', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    GuidelinePresets::applyToProject($project, 'universal_s1');

    $chapter = $project->rootChapters()->first();
    $section = $chapter->sections()->first();

    // Delete a section
    $response = $this->actingAs($user)->delete("/projects/{$project->id}/chapters/{$section->id}");
    $response->assertRedirect();
    $this->assertDatabaseMissing('chapters', ['id' => $section->id]);
    $this->assertDatabaseHas('chapters', ['id' => $chapter->id]);

    // Delete a chapter
    $remainingSection = $chapter->sections()->first();
    $response = $this->actingAs($user)->delete("/projects/{$project->id}/chapters/{$chapter->id}");
    $response->assertRedirect();
    $this->assertDatabaseMissing('chapters', ['id' => $chapter->id]);
    $this->assertDatabaseMissing('chapters', ['id' => $remainingSection->id]);
});

test('unauthorized user cannot modify outline of another users project', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();

    $project = Project::factory()->create(['user_id' => $owner->id]);
    GuidelinePresets::applyToProject($project, 'universal_s1');
    $chapter = $project->rootChapters()->first();

    $response = $this->actingAs($attacker)->patch("/projects/{$project->id}/chapters/{$chapter->id}", [
        'title' => 'Attacker Title',
    ]);
    $response->assertForbidden();

    $response = $this->actingAs($attacker)->post("/projects/{$project->id}/guideline-preset", [
        'preset' => 'itb',
    ]);
    $response->assertForbidden();

    $response = $this->actingAs($attacker)->delete("/projects/{$project->id}/chapters/{$chapter->id}");
    $response->assertForbidden();
});

test('all five guideline presets successfully populate bab 1 through bab 5 per project', function (string $presetKey) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->post("/projects/{$project->id}/guideline-preset", [
        'preset' => $presetKey,
    ]);

    $response->assertRedirect();

    $project->refresh();
    expect($project->guideline_preset)->toBe($presetKey);
    expect($project->guideline_profile)->not->toBeNull();

    $chapters = $project->rootChapters()->with('sections')->get();
    expect($chapters)->toHaveCount(5);
    foreach ($chapters as $chapter) {
        expect($chapter->title)->not->toBeEmpty();
        expect($chapter->sections)->not->toBeEmpty();
    }
})->with(['universal_s1', 'ui', 'ugm', 'itb', 'gunadarma']);

test('projects show page renders with chapters sections and presets', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);
    GuidelinePresets::applyToProject($project, 'universal_s1');

    $response = $this->actingAs($user)->get("/projects/{$project->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Projects/Show')
        ->has('project')
        ->has('chapters', 5)
        ->has('guidelinePresets')
    );
});
