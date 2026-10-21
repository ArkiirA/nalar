<?php

use App\Models\Reference;
use App\Support\CslFormatter;

test('formats single author journal article in APA 7th style', function () {
    $ref = new Reference([
        'type' => 'article-journal',
        'title' => 'Penerapan Machine Learning Pada Sistem Skripsi',
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'Agus'],
        ],
        'year' => 2023,
        'publication_title' => 'Jurnal Teknologi Informasi',
        'volume' => '15',
        'issue' => '2',
        'pages' => '101-112',
        'doi' => '10.1234/jti.2023.01',
    ]);

    $formatted = CslFormatter::formatApa7($ref);

    expect($formatted)->toBe('Wijaya, A. (2023). Penerapan Machine Learning Pada Sistem Skripsi. Jurnal Teknologi Informasi, 15(2), 101-112. https://doi.org/10.1234/jti.2023.01');
});

test('formats two authors journal article in APA 7th style using ampersand', function () {
    $ref = new Reference([
        'type' => 'article-journal',
        'title' => 'Analisis Algoritma Hybrid RRF',
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'Agus'],
            ['family' => 'Pratama', 'given' => 'Budi'],
        ],
        'year' => 2024,
        'publication_title' => 'Jurnal Ilmu Komputer',
        'volume' => '8',
        'issue' => '1',
        'pages' => '45-56',
    ]);

    $formatted = CslFormatter::formatApa7($ref);

    expect($formatted)->toBe('Wijaya, A., & Pratama, B. (2024). Analisis Algoritma Hybrid RRF. Jurnal Ilmu Komputer, 8(1), 45-56.');
});

test('formats three or more authors in APA 7th style with ampersand before last author', function () {
    $ref = new Reference([
        'type' => 'article-journal',
        'title' => 'Evaluasi Kinerja Pengindeksan Berkas',
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'A.'],
            ['family' => 'Pratama', 'given' => 'B.'],
            ['family' => 'Santoso', 'given' => 'C.'],
        ],
        'year' => 2022,
        'publication_title' => 'Jurnal Rekayasa Perangkat Lunak',
    ]);

    $formatted = CslFormatter::formatApa7($ref);

    expect($formatted)->toBe('Wijaya, A., Pratama, B., & Santoso, C. (2022). Evaluasi Kinerja Pengindeksan Berkas. Jurnal Rekayasa Perangkat Lunak.');
});

test('formats book in APA 7th style', function () {
    $ref = new Reference([
        'type' => 'book',
        'title' => 'Metode Penelitian Kualitatif dan Kuantitatif',
        'authors' => [
            ['family' => 'Sugiyono', 'given' => ''],
        ],
        'year' => 2019,
        'publisher' => 'Alfabeta',
        'publisher_place' => 'Bandung',
    ]);

    $formatted = CslFormatter::formatApa7($ref);

    expect($formatted)->toBe('Sugiyono. (2019). Metode Penelitian Kualitatif dan Kuantitatif. Alfabeta.');
});

test('formats conference paper in APA 7th style', function () {
    $ref = new Reference([
        'type' => 'paper-conference',
        'title' => 'Perancangan Sistem Rekomendasi Terdistribusi',
        'authors' => [
            ['family' => 'Hidayat', 'given' => 'Rahmat'],
        ],
        'year' => 2021,
        'publication_title' => 'Prosiding Seminar Nasional Ilmu Komputer',
        'pages' => '23-30',
        'publisher' => 'Ikatan Ilmuwan Indonesia',
    ]);

    $formatted = CslFormatter::formatApa7($ref);

    expect($formatted)->toBe('Hidayat, R. (2021). Perancangan Sistem Rekomendasi Terdistribusi. In Prosiding Seminar Nasional Ilmu Komputer (pp. 23-30). Ikatan Ilmuwan Indonesia.');
});

test('formats journal article in Harvard style', function () {
    $ref = new Reference([
        'type' => 'article-journal',
        'title' => 'Penerapan Machine Learning Pada Sistem Skripsi',
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'Agus'],
            ['family' => 'Pratama', 'given' => 'Budi'],
        ],
        'year' => 2023,
        'publication_title' => 'Jurnal Teknologi Informasi',
        'volume' => '15',
        'issue' => '2',
        'pages' => '101-112',
    ]);

    $formatted = CslFormatter::formatHarvard($ref);

    expect($formatted)->toBe("Wijaya, A. and Pratama, B. (2023) 'Penerapan Machine Learning Pada Sistem Skripsi', Jurnal Teknologi Informasi, vol. 15, no. 2, pp. 101-112.");
});

test('formats book in Harvard style', function () {
    $ref = new Reference([
        'type' => 'book',
        'title' => 'Metode Penelitian Kualitatif dan Kuantitatif',
        'authors' => [
            ['family' => 'Sugiyono', 'given' => ''],
        ],
        'year' => 2019,
        'publisher' => 'Alfabeta',
        'publisher_place' => 'Bandung',
    ]);

    $formatted = CslFormatter::formatHarvard($ref);

    expect($formatted)->toBe('Sugiyono (2019) Metode Penelitian Kualitatif dan Kuantitatif. Bandung: Alfabeta.');
});

test('formats in-text citations correctly for both styles', function () {
    $oneAuthor = new Reference([
        'authors' => [['family' => 'Wijaya', 'given' => 'Agus']],
        'year' => 2023,
    ]);

    $twoAuthors = new Reference([
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'Agus'],
            ['family' => 'Pratama', 'given' => 'Budi'],
        ],
        'year' => 2023,
    ]);

    $threeAuthors = new Reference([
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'Agus'],
            ['family' => 'Pratama', 'given' => 'Budi'],
            ['family' => 'Santoso', 'given' => 'Cahyo'],
        ],
        'year' => 2023,
    ]);

    expect(CslFormatter::inTextCitation($oneAuthor, 'apa'))->toBe('(Wijaya, 2023)');
    expect(CslFormatter::inTextCitation($twoAuthors, 'apa'))->toBe('(Wijaya & Pratama, 2023)');
    expect(CslFormatter::inTextCitation($threeAuthors, 'apa'))->toBe('(Wijaya et al., 2023)');

    expect(CslFormatter::inTextCitation($oneAuthor, 'harvard'))->toBe('(Wijaya, 2023)');
    expect(CslFormatter::inTextCitation($twoAuthors, 'harvard'))->toBe('(Wijaya and Pratama, 2023)');
    expect(CslFormatter::inTextCitation($threeAuthors, 'harvard'))->toBe('(Wijaya et al., 2023)');
});

test('serializes and deserializes CSL-JSON format faithfully', function () {
    $csl = [
        'id' => 'ref-01',
        'type' => 'article-journal',
        'title' => 'Studi Komparasi Algoritma Stemming Bahasa Indonesia',
        'author' => [
            ['family' => 'Tala', 'given' => 'F. Z.'],
        ],
        'issued' => [
            'date-parts' => [[2003]],
        ],
        'container-title' => 'ACM Transactions on Asian Language Information Processing',
        'volume' => '2',
        'issue' => '1',
        'page' => '35-51',
        'DOI' => '10.1145/958988.958992',
    ];

    $attributes = Reference::fromCslJson($csl);

    expect($attributes['title'])->toBe('Studi Komparasi Algoritma Stemming Bahasa Indonesia');
    expect($attributes['year'])->toBe(2003);
    expect($attributes['publication_title'])->toBe('ACM Transactions on Asian Language Information Processing');
    expect($attributes['volume'])->toBe('2');
    expect($attributes['pages'])->toBe('35-51');
    expect($attributes['doi'])->toBe('10.1145/958988.958992');

    $reference = new Reference($attributes);
    $serialized = $reference->toCslJson();

    expect($serialized['type'])->toBe('article-journal');
    expect($serialized['title'])->toBe('Studi Komparasi Algoritma Stemming Bahasa Indonesia');
    expect($serialized['issued']['date-parts'][0][0])->toBe(2003);
    expect($serialized['DOI'])->toBe('10.1145/958988.958992');
});

test('formats Harvard style with DOI and URL appropriately', function () {
    $ref = new Reference([
        'type' => 'article-journal',
        'title' => 'Penerapan Machine Learning Pada Sistem Skripsi',
        'authors' => [
            ['family' => 'Wijaya', 'given' => 'Agus'],
        ],
        'year' => 2023,
        'publication_title' => 'Jurnal Teknologi Informasi',
        'doi' => '10.1234/jti.2023.01',
    ]);

    $formatted = CslFormatter::formatHarvard($ref);

    expect($formatted)->toBe("Wijaya, A. (2023) 'Penerapan Machine Learning Pada Sistem Skripsi', Jurnal Teknologi Informasi. Available at: https://doi.org/10.1234/jti.2023.01.");
});

test('generates deterministic stable reference key from first author and year', function () {
    $ref = new Reference([
        'authors' => [
            ['family' => 'Turing', 'given' => 'Alan'],
        ],
        'year' => 1950,
    ]);

    expect($ref->generateReferenceKey())->toBe('turing1950');
});

