<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentPage;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentPage>
 */
class DocumentPageFactory extends Factory
{
    protected $model = DocumentPage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $content = fake()->paragraphs(3, true);

        return [
            'project_id' => Project::factory(),
            'document_id' => Document::factory(),
            'page_number' => 1,
            'content' => $content,
            'character_count' => mb_strlen($content),
        ];
    }
}
