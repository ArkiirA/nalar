<?php

use App\Services\DocumentChunker;

test('short page content produces a single candidate passage with token count', function () {
    $chunker = new DocumentChunker();
    $text = "Bab I Pendahuluan.\n\nPenelitian ini membahas tentang kecerdasan buatan dalam penulisan skripsi mahasiswa.";

    $chunks = $chunker->chunkText($text, pageNumber: 1);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]['page_number'])->toBe(1);
    expect($chunks[0]['chunk_index'])->toBe(0);
    expect($chunks[0]['content'])->toBe(trim($text));
    expect($chunks[0]['token_count'])->toBeGreaterThan(0);
    expect($chunks[0]['token_count'])->toBeLessThan(500);
});

test('empty or whitespace text produces zero chunks', function () {
    $chunker = new DocumentChunker();

    expect($chunker->chunkText('', pageNumber: 1))->toBeEmpty();
    expect($chunker->chunkText("   \n\n  \t ", pageNumber: 2))->toBeEmpty();
});

test('multi-paragraph text exceeding target token size splits along paragraph boundaries', function () {
    $chunker = new DocumentChunker();

    // Paragraph 1 ~270 tokens
    $p1 = str_repeat('Kata pertama dalam paragraf satu untuk uji coba pembagian teks akademik. ', 15);
    // Paragraph 2 ~270 tokens
    $p2 = str_repeat('Kata kedua dalam paragraf dua untuk menganalisis pemecahan dokumen berdasar alinea. ', 15);
    $text = $p1 . "\n\n" . $p2;

    $chunks = $chunker->chunkText($text, pageNumber: 3, targetTokens: 400);

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['page_number'])->toBe(3);
    expect($chunks[0]['chunk_index'])->toBe(0);
    expect($chunks[0]['content'])->toBe(trim($p1));
    expect($chunks[0]['token_count'])->toBeGreaterThanOrEqual(200);

    expect($chunks[1]['page_number'])->toBe(3);
    expect($chunks[1]['chunk_index'])->toBe(1);
    expect($chunks[1]['content'])->toBe(trim($p2));
    expect($chunks[1]['token_count'])->toBeGreaterThanOrEqual(200);
});

test('chunking multiple pages strictly preserves physical page numbers without crossing boundaries', function () {
    $chunker = new DocumentChunker();

    $pages = [
        ['page_number' => 1, 'text' => 'Teks halaman satu skripsi.'],
        ['page_number' => 2, 'text' => 'Teks halaman dua tinjauan pustaka.'],
        ['page_number' => 3, 'text' => '   '], // Blank page
        ['page_number' => 4, 'text' => 'Teks halaman empat metodologi penelitian.'],
    ];

    $chunks = $chunker->chunkPages($pages);

    expect($chunks)->toHaveCount(3);
    expect($chunks[0]['page_number'])->toBe(1);
    expect($chunks[0]['chunk_index'])->toBe(0);
    expect($chunks[0]['content'])->toBe('Teks halaman satu skripsi.');

    expect($chunks[1]['page_number'])->toBe(2);
    expect($chunks[1]['chunk_index'])->toBe(1);
    expect($chunks[1]['content'])->toBe('Teks halaman dua tinjauan pustaka.');

    expect($chunks[2]['page_number'])->toBe(4);
    expect($chunks[2]['chunk_index'])->toBe(2);
    expect($chunks[2]['content'])->toBe('Teks halaman empat metodologi penelitian.');
});

test('oversized paragraph splits along sentence boundaries without exceeding token thresholds', function () {
    $chunker = new DocumentChunker();

    $sentence1 = 'Kalimat pertama menjelaskan latar belakang masalah penelitian yang sangat mendalam. ';
    $sentence2 = 'Kalimat kedua memberikan bukti empiris mengenai fenomena yang diteliti di lapangan. ';
    $sentence3 = 'Kalimat ketiga menyimpulkan bahwa intervensi teknologi sangat diperlukan segera. ';

    $giantParagraph = str_repeat($sentence1, 20) . str_repeat($sentence2, 20) . str_repeat($sentence3, 20);

    $chunks = $chunker->chunkText($giantParagraph, pageNumber: 5, targetTokens: 300);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect($chunk['page_number'])->toBe(5);
        expect($chunk['token_count'])->toBeLessThanOrEqual(550);
    }
});
