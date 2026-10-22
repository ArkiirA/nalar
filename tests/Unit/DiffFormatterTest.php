<?php

use App\Support\DiffFormatter;

test('diff formatter reports no changes for identical strings', function () {
    $text = 'Metode penelitian menggunakan pendekatan kualitatif.';
    $result = DiffFormatter::format($text, $text);

    expect($result['has_changes'])->toBeFalse()
        ->and($result['original'])->toBe($text)
        ->and($result['proposed'])->toBe($text)
        ->and($result['stats']['additions'])->toBe(0)
        ->and($result['stats']['deletions'])->toBe(0)
        ->and($result['stats']['unchanged'])->toBe(mb_strlen($text))
        ->and($result['chunks'])->toHaveCount(1)
        ->and($result['chunks'][0]['type'])->toBe('common')
        ->and($result['chunks'][0]['value'])->toBe($text);
});

test('diff formatter handles empty inputs cleanly', function () {
    $result = DiffFormatter::format('', '');

    expect($result['has_changes'])->toBeFalse()
        ->and($result['stats']['additions'])->toBe(0)
        ->and($result['stats']['deletions'])->toBe(0)
        ->and($result['chunks'])->toBeEmpty();
});

test('diff formatter detects pure additions', function () {
    $original = 'Penelitian ini bertujuan.';
    $proposed = 'Penelitian ini bertujuan untuk menguji hipotesis.';

    $result = DiffFormatter::format($original, $proposed);

    expect($result['has_changes'])->toBeTrue()
        ->and($result['stats']['additions'])->toBeGreaterThan(0)
        ->and($result['stats']['deletions'])->toBe(0);

    $addedChunks = array_filter($result['chunks'], fn ($c) => $c['type'] === 'added');
    expect($addedChunks)->not->toBeEmpty();
});

test('diff formatter detects pure deletions', function () {
    $original = 'Penelitian ini sebenarnya sangat penting sekali.';
    $proposed = 'Penelitian ini penting.';

    $result = DiffFormatter::format($original, $proposed);

    expect($result['has_changes'])->toBeTrue()
        ->and($result['stats']['deletions'])->toBeGreaterThan(0);

    $removedChunks = array_filter($result['chunks'], fn ($c) => $c['type'] === 'removed');
    expect($removedChunks)->not->toBeEmpty();
});

test('diff formatter highlights replacements with additions and deletions', function () {
    $original = 'Metode penelitian kualitatif deskriptif.';
    $proposed = 'Metode penelitian kuantitatif inferensial.';

    $result = DiffFormatter::format($original, $proposed);

    expect($result['has_changes'])->toBeTrue()
        ->and($result['stats']['additions'])->toBeGreaterThan(0)
        ->and($result['stats']['deletions'])->toBeGreaterThan(0);

    $types = array_column($result['chunks'], 'type');
    expect($types)->toContain('common')
        ->and($types)->toContain('removed')
        ->and($types)->toContain('added');
});
