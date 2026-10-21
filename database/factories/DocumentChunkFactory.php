<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentChunk>
 */
class DocumentChunkFactory extends Factory
{
    protected $model = DocumentChunk::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'document_id' => Document::factory(),
            'document_page_id' => null,
            'page_number' => 1,
            'chunk_index' => 0,
            'content' => fake()->paragraph(),
            'token_count' => fake()->numberBetween(50, 450),
            'embedding' => null,
        ];
    }

    /**
     * Set a dummy 768-dimensional vector embedding.
     */
    public function withEmbedding(?array $vector = null): static
    {
        return $this->state(fn (array $attributes) => [
            'embedding' => $vector ?? array_fill(0, 768, 0.01),
        ]);
    }
}
