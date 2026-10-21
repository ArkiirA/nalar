<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Reference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reference>
 */
class ReferenceFactory extends Factory
{
    protected $model = Reference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'document_id' => null,
            'reference_key' => fake()->unique()->slug(2),
            'type' => 'article-journal',
            'title' => fake()->sentence(5),
            'authors' => [
                ['family' => fake()->lastName(), 'given' => fake()->firstName()],
            ],
            'year' => fake()->numberBetween(2010, 2025),
            'publication_title' => 'Jurnal '.fake()->word(),
            'volume' => (string) fake()->numberBetween(1, 20),
            'issue' => (string) fake()->numberBetween(1, 4),
            'pages' => '1-15',
            'publisher' => fake()->company(),
            'doi' => '10.'.fake()->numberBetween(1000, 9999).'/'.fake()->slug(2),
            'url' => fake()->url(),
            'csl_data' => null,
            'is_confirmed' => true,
        ];
    }
}
