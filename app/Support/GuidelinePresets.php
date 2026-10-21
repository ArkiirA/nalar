<?php

namespace App\Support;

use App\Models\Chapter;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuidelinePresets
{
    /**
     * Common formatting settings helper.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function defaultFormatting(array $overrides = []): array
    {
        return array_replace_recursive([
            'font_family' => 'Times New Roman',
            'font_size' => '12pt',
            'line_spacing' => '1.5',
            'paper_size' => 'A4',
            'margins' => [
                'top' => '4 cm',
                'left' => '4 cm',
                'bottom' => '3 cm',
                'right' => '3 cm',
            ],
        ], $overrides);
    }

    /**
     * Get all available guideline presets.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'universal_s1' => [
                'id' => 'universal_s1',
                'name' => 'Universal S1 (Standar Indonesia)',
                'university' => 'Standar Nasional Perguruan Tinggi',
                'description' => 'Pedoman skripsi format baku 5 bab untuk jenjang Sarjana (S1) di Indonesia.',
                'citation_style' => 'APA 7th',
                'formatting' => self::defaultFormatting(),
                'outline' => [
                    [
                        'title' => 'BAB I PENDAHULUAN',
                        'sections' => [
                            '1.1 Latar Belakang Masalah',
                            '1.2 Identifikasi dan Rumusan Masalah',
                            '1.3 Batasan Masalah',
                            '1.4 Tujuan Penelitian',
                            '1.5 Manfaat Penelitian',
                            '1.6 Sistematika Penulisan',
                        ],
                    ],
                    [
                        'title' => 'BAB II TINJAUAN PUSTAKA',
                        'sections' => [
                            '2.1 Landasan Teori',
                            '2.2 Penelitian Terdahulu',
                            '2.3 Kerangka Berpikir',
                            '2.4 Hipotesis Penelitian',
                        ],
                    ],
                    [
                        'title' => 'BAB III METODOLOGI PENELITIAN',
                        'sections' => [
                            '3.1 Desain Penelitian',
                            '3.2 Waktu dan Tempat Penelitian',
                            '3.3 Populasi dan Sampel',
                            '3.4 Teknik Pengumpulan Data',
                            '3.5 Metode Analisis Data',
                        ],
                    ],
                    [
                        'title' => 'BAB IV HASIL DAN PEMBAHASAN',
                        'sections' => [
                            '4.1 Deskripsi Hasil Penelitian',
                            '4.2 Analisis dan Pengujian Sistem',
                            '4.3 Pembahasan Temuan Riset',
                        ],
                    ],
                    [
                        'title' => 'BAB V PENUTUP',
                        'sections' => [
                            '5.1 Kesimpulan',
                            '5.2 Saran',
                        ],
                    ],
                ],
            ],

            'ui' => [
                'id' => 'ui',
                'name' => 'Universitas Indonesia (UI)',
                'university' => 'Universitas Indonesia',
                'description' => 'Pedoman Teknis Penulisan Tugas Akhir Sivitas Akademika Universitas Indonesia.',
                'citation_style' => 'APA 7th',
                'formatting' => self::defaultFormatting(),
                'outline' => [
                    [
                        'title' => 'BAB 1 PENDAHULUAN',
                        'sections' => [
                            '1.1 Latar Belakang Masalah',
                            '1.2 Pokok Permasalahan',
                            '1.3 Tujuan Penelitian',
                            '1.4 Metode Penelitian',
                            '1.5 Sistematika Penulisan',
                        ],
                    ],
                    [
                        'title' => 'BAB 2 LANDASAN TEORI',
                        'sections' => [
                            '2.1 Tinjauan Pustaka',
                            '2.2 Kerangka Teoretis',
                            '2.3 Kerangka Pemikiran',
                        ],
                    ],
                    [
                        'title' => 'BAB 3 METODOLOGI PENELITIAN',
                        'sections' => [
                            '3.1 Pendekatan Penelitian',
                            '3.2 Teknik Pengumpulan Data',
                            '3.3 Metode Analisis Data',
                        ],
                    ],
                    [
                        'title' => 'BAB 4 ANALISIS DAN PEMBAHASAN',
                        'sections' => [
                            '4.1 Gambaran Umum Objek Penelitian',
                            '4.2 Analisis Hasil Penelitian',
                            '4.3 Pembahasan Temuan',
                        ],
                    ],
                    [
                        'title' => 'BAB 5 KESIMPULAN DAN SARAN',
                        'sections' => [
                            '5.1 Kesimpulan',
                            '5.2 Saran Rekomendasi',
                        ],
                    ],
                ],
            ],

            'ugm' => [
                'id' => 'ugm',
                'name' => 'Universitas Gadjah Mada (UGM)',
                'university' => 'Universitas Gadjah Mada',
                'description' => 'Format pedoman penulisan tesis dan skripsi Universitas Gadjah Mada.',
                'citation_style' => 'Harvard / APA 7th',
                'formatting' => self::defaultFormatting(),
                'outline' => [
                    [
                        'title' => 'BAB I PENDAHULUAN',
                        'sections' => [
                            '1.1 Latar Belakang',
                            '1.2 Rumusan Masalah',
                            '1.3 Batasan Masalah',
                            '1.4 Keaslian Penelitian',
                            '1.5 Tujuan Penelitian',
                            '1.6 Manfaat Penelitian',
                        ],
                    ],
                    [
                        'title' => 'BAB II TINJAUAN PUSTAKA DAN LANDASAN TEORI',
                        'sections' => [
                            '2.1 Tinjauan Pustaka',
                            '2.2 Landasan Teori',
                            '2.3 Landasan Hipotesis',
                        ],
                    ],
                    [
                        'title' => 'BAB III METODE PENELITIAN',
                        'sections' => [
                            '3.1 Bahan dan Alat Penelitian',
                            '3.2 Prosedur dan Cara Penelitian',
                            '3.3 Analisis Data',
                            '3.4 Kesulitan dan Pemecahan Masalah',
                        ],
                    ],
                    [
                        'title' => 'BAB IV HASIL DAN PEMBAHASAN',
                        'sections' => [
                            '4.1 Hasil Penelitian',
                            '4.2 Pembahasan',
                        ],
                    ],
                    [
                        'title' => 'BAB V KESIMPULAN DAN SARAN',
                        'sections' => [
                            '5.1 Kesimpulan',
                            '5.2 Saran',
                        ],
                    ],
                ],
            ],

            'itb' => [
                'id' => 'itb',
                'name' => 'Institut Teknologi Bandung (ITB)',
                'university' => 'Institut Teknologi Bandung',
                'description' => 'Pedoman Penulisan Tugas Akhir Sarjana Institut Teknologi Bandung.',
                'citation_style' => 'IEEE / Harvard',
                'formatting' => self::defaultFormatting([
                    'margins' => [
                        'top' => '3 cm',
                        'left' => '4 cm',
                        'bottom' => '3 cm',
                        'right' => '3 cm',
                    ],
                ]),
                'outline' => [
                    [
                        'title' => 'BAB 1 PENDAHULUAN',
                        'sections' => [
                            '1.1 Latar Belakang',
                            '1.2 Perumusan Masalah',
                            '1.3 Tujuan dan Sasaran',
                            '1.4 Ruang Lingkup dan Batasan',
                            '1.5 Metodologi Pelaksanaan',
                            '1.6 Sistematika Pembahasan',
                        ],
                    ],
                    [
                        'title' => 'BAB 2 KAJIAN PUSTAKA',
                        'sections' => [
                            '2.1 Kajian Pustaka',
                            '2.2 Landasan Teori Pendukung',
                        ],
                    ],
                    [
                        'title' => 'BAB 3 METODOLOGI DAN PERANCANGAN',
                        'sections' => [
                            '3.1 Model Konseptual',
                            '3.2 Perancangan Desain Sistem',
                            '3.3 Rencana dan Parameter Pengujian',
                        ],
                    ],
                    [
                        'title' => 'BAB 4 IMPLEMENTASI DAN EVALUASI',
                        'sections' => [
                            '4.1 Implementasi Sistem',
                            '4.2 Hasil Pengujian',
                            '4.3 Evaluasi dan Diskusi',
                        ],
                    ],
                    [
                        'title' => 'BAB 5 KESIMPULAN DAN REKOMENDASI',
                        'sections' => [
                            '5.1 Kesimpulan',
                            '5.2 Saran dan Pengembangan Lanjutan',
                        ],
                    ],
                ],
            ],

            'gunadarma' => [
                'id' => 'gunadarma',
                'name' => 'Universitas Gunadarma',
                'university' => 'Universitas Gunadarma',
                'description' => 'Buku Pedoman Penulisan Skripsi dan Penulisan Ilmiah Universitas Gunadarma (laporan-generator-thesis).',
                'citation_style' => 'APA 7th',
                'formatting' => self::defaultFormatting([
                    'heading_chapter_prefix' => '',
                    'heading_chapter_num_format' => 'arabic',
                ]),
                'outline' => [
                    [
                        'title' => '1. PENDAHULUAN',
                        'sections' => [
                            '1.1 Latar Belakang',
                            '1.2 Ruang Lingkup',
                            '1.3 Tujuan Penelitian',
                            '1.4 Manfaat Penelitian',
                            '1.5 Sistematika Penulisan',
                        ],
                    ],
                    [
                        'title' => '2. LANDASAN TEORI',
                        'sections' => [
                            '2.1 Landasan Teori',
                            '2.2 Tinjauan Penelitian Terdahulu',
                        ],
                    ],
                    [
                        'title' => '3. METODOLOGI PENELITIAN',
                        'sections' => [
                            '3.1 Kerangka Penelitian',
                            '3.2 Analisis Kebutuhan',
                            '3.3 Desain dan Perancangan Sistem',
                            '3.4 Rencana Pengujian',
                        ],
                    ],
                    [
                        'title' => '4. HASIL DAN PEMBAHASAN',
                        'sections' => [
                            '4.1 Implementasi Sistem',
                            '4.2 Pengujian dan Evaluasi',
                            '4.3 Pembahasan Temuan',
                        ],
                    ],
                    [
                        'title' => '5. PENUTUP',
                        'sections' => [
                            '5.1 Kesimpulan',
                            '5.2 Saran',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Find a preset by key.
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Apply a guideline preset to a project and generate default chapters/sections.
     */
    public static function applyToProject(Project $project, string $presetKey): void
    {
        $preset = self::find($presetKey);

        if (! $preset) {
            throw new InvalidArgumentException("Unknown guideline preset: {$presetKey}");
        }

        DB::transaction(function () use ($project, $presetKey, $preset) {
            $project->update([
                'guideline_preset' => $presetKey,
                'guideline_profile' => [
                    'name' => $preset['name'],
                    'university' => $preset['university'],
                    'citation_style' => $preset['citation_style'],
                    'formatting' => $preset['formatting'],
                ],
            ]);

            // Clear existing outline items to apply new preset
            $project->outlineItems()->delete();

            foreach ($preset['outline'] as $chapterIndex => $chapterData) {
                $chapter = Chapter::create([
                    'project_id' => $project->id,
                    'parent_id' => null,
                    'title' => $chapterData['title'],
                    'order' => $chapterIndex + 1,
                ]);

                if (! empty($chapterData['sections'])) {
                    foreach ($chapterData['sections'] as $sectionIndex => $sectionTitle) {
                        Chapter::create([
                            'project_id' => $project->id,
                            'parent_id' => $chapter->id,
                            'title' => $sectionTitle,
                            'order' => $sectionIndex + 1,
                        ]);
                    }
                }
            }
        });
    }
}
