<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SupervisionNote;
use App\Models\SupervisionTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupervisionTask>
 */
class SupervisionTaskFactory extends Factory
{
    protected $model = SupervisionTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'supervision_note_id' => SupervisionNote::factory(),
            'chapter_id' => null,
            'title' => fake()->sentence(4),
            'is_completed' => false,
            'completed_at' => null,
        ];
    }
}
