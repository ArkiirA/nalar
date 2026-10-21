<?php

use App\Models\Chapter;
use App\Models\Project;
use App\Models\User;
use App\Services\Export\TypstThesisCompiler;

test('typst compiler generates valid thesis layout with 4-4-3-3 margins', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);
    $project = Project::factory()->create([
        'user_id' => $user->id,
        'title' => 'Sistem Deteksi Anomali Jaringan Berbasis Machine Learning',
        'guideline_preset' => 'gunadarma',
    ]);
    $project->applyGuidelinePreset('gunadarma');

    // Add content to Bab 1
    $chapter1 = $project->rootChapters()->first();
    $chapter1->update([
        'content' => [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Latar belakang penelitian ini disusun menggunakan '],
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'metode ilmiah'],
                        ['type' => 'text', 'text' => ' yang terverifikasi.'],
                    ],
                ],
            ],
        ],
    ]);

    $compiler = new TypstThesisCompiler();
    $typst = $compiler->compile($project);

    // Verify paper, margin 4-4-3-3, font TNR
    expect($typst)->toContain('margin: (top: 4 cm, bottom: 3 cm, left: 4 cm, right: 3 cm)');
    expect($typst)->toContain('Times New Roman');
    expect($typst)->toContain('Budi Santoso');
    expect($typst)->toContain('*metode ilmiah*');
    expect($typst)->toContain('PENDAHULUAN');
});

test('typst compiler correctly converts tables and bullet lists', function () {
    $compiler = new TypstThesisCompiler();

    $content = [
        'type' => 'doc',
        'content' => [
            [
                'type' => 'bulletList',
                'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'text', 'text' => 'Poin Pertama']]],
                    ['type' => 'listItem', 'content' => [['type' => 'text', 'text' => 'Poin Kedua']]],
                ],
            ],
            [
                'type' => 'table',
                'content' => [
                    [
                        'type' => 'tableRow',
                        'content' => [
                            ['type' => 'tableHeader', 'content' => [['type' => 'text', 'text' => 'No']]],
                            ['type' => 'tableHeader', 'content' => [['type' => 'text', 'text' => 'Parameter']]],
                        ],
                    ],
                    [
                        'type' => 'tableRow',
                        'content' => [
                            ['type' => 'tableCell', 'content' => [['type' => 'text', 'text' => '1']]],
                            ['type' => 'tableCell', 'content' => [['type' => 'text', 'text' => 'Akurasi']]],
                        ],
                    ],
                ],
            ],
        ],
    ];

    $output = $compiler->convertTiptapToTypst($content);

    expect($output)->toContain('- Poin Pertama');
    expect($output)->toContain('- Poin Kedua');
    expect($output)->toContain('#table(');
    expect($output)->toContain('table.header([*No*])');
    expect($output)->toContain('table.header([*Parameter*])');
    expect($output)->toContain('[Akurasi]');
});
