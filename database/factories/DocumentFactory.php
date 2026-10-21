<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'original_filename' => fake()->word().'.pdf',
            'file_path' => 'projects/test/documents/sample.pdf',
            'file_size' => fake()->numberBetween(1024, 1024 * 1024 * 5),
            'mime_type' => 'application/pdf',
            'total_pages' => fake()->numberBetween(1, 10),
            'status' => Document::STATUS_QUEUED,
            'error_message' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_QUEUED,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_PROCESSING,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_PROCESSED,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_PARTIAL,
            'error_message' => 'Sebagian halaman tidak dapat diekstrak.',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Document::STATUS_FAILED,
            'error_message' => 'Gagal mengekstrak teks dari dokumen.',
        ]);
    }
}
