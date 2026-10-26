<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\Reference;
use App\Models\User;
use App\Services\ExportIntegrityValidator;

test('validator detects uncited references, invalid citations, and empty chapters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    // 1. Reference cited properly
    $citedRef = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Machine Learning for Education',
        'authors' => [['family' => 'Pratama', 'given' => 'Ahmad']],
        'year' => 2023,
    ]);

    // 2. Reference uncited
    $uncitedRef = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Uncited Academic Work',
        'authors' => [['family' => 'Santoso', 'given' => 'Budi']],
        'year' => 2022,
    ]);

    // 3. Chapter 1 with valid citation and invalid citation (reference_id 999999)
    $chapter1 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'order' => 1,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Berdasarkan kajian terdahulu '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $citedRef->id,
                                'label' => '(Pratama, 2023)',
                            ],
                        ],
                        ['type' => 'text', 'text' => ' serta temuan '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => 999999, // Non-existent reference
                                'label' => '(Unknown, 2020)',
                            ],
                        ],
                        ['type' => 'text', 'text' => ', sistem ini dikembangkan.'],
                    ],
                ],
            ],
        ],
    ]);

    // 4. Chapter 2 with empty content
    $chapter2 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB II TINJAUAN PUSTAKA',
        'order' => 2,
        'content' => null,
    ]);

    // 5. Chapter 3 with whitespace-only content
    $chapter3 = Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB III METODOLOGI PENELITIAN',
        'order' => 3,
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => '     '],
                    ],
                ],
            ],
        ],
    ]);

    $validator = app(ExportIntegrityValidator::class);
    $report = $validator->validate($project);

    expect($report['is_valid'])->toBeFalse();
    expect($report['summary']['uncited_references_count'])->toBe(1);
    expect($report['summary']['invalid_citations_count'])->toBe(1);
    expect($report['summary']['empty_chapters_count'])->toBe(2);

    expect(collect($report['uncited_references'])->pluck('id')->all())->toContain($uncitedRef->id);
    expect(collect($report['uncited_references'])->pluck('id')->all())->not->toContain($citedRef->id);

    expect(collect($report['invalid_citations'])->pluck('reference_id')->all())->toContain(999999);

    $emptyTitles = collect($report['empty_chapters'])->pluck('title')->all();
    expect($emptyTitles)->toContain('BAB II TINJAUAN PUSTAKA');
    expect($emptyTitles)->toContain('BAB III METODOLOGI PENELITIAN');
});

test('validator reports valid when all chapters have content, citations exist, and no uncited references', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $ref = Reference::factory()->create([
        'project_id' => $project->id,
        'title' => 'Studi Komparasi Algoritma',
    ]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Kajian teoritis '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => $ref->id,
                                'label' => '(Ref, 2024)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $validator = app(ExportIntegrityValidator::class);
    $report = $validator->validate($project);

    expect($report['is_valid'])->toBeTrue();
    expect($report['summary']['total_warnings_count'])->toBe(0);
    expect($report['empty_chapters'])->toBeEmpty();
    expect($report['uncited_references'])->toBeEmpty();
    expect($report['invalid_citations'])->toBeEmpty();
});

test('validator detects citation nodes without reference_id attribute', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Sitasi rusak '],
                        [
                            'type' => 'citation',
                            'attrs' => [
                                'reference_id' => null,
                                'label' => '(Broken, ?)',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $validator = app(ExportIntegrityValidator::class);
    $report = $validator->validate($project);

    expect($report['is_valid'])->toBeFalse();
    expect($report['summary']['invalid_citations_count'])->toBe(1);
    expect($report['invalid_citations'][0]['reference_id'])->toBeNull();
    expect($report['warnings'])->toContain("Sitasi tanpa ID referensi pada 'BAB I PENDAHULUAN' tidak valid.");
});

test('export validate endpoint returns integrity report json for authorized user', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    Chapter::factory()->create([
        'project_id' => $project->id,
        'title' => 'BAB I PENDAHULUAN',
        'content' => null,
    ]);

    $response = $this->actingAs($user)->getJson("/projects/{$project->id}/export/validate");

    $response->assertOk();
    $response->assertJsonStructure([
        'is_valid',
        'summary' => [
            'empty_chapters_count',
            'uncited_references_count',
            'invalid_citations_count',
            'total_warnings_count',
        ],
        'empty_chapters',
        'uncited_references',
        'invalid_citations',
        'warnings',
    ]);
});

test('export validate endpoint forbids unauthorized users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($other)->getJson("/projects/{$project->id}/export/validate");
    $response->assertForbidden();
});
