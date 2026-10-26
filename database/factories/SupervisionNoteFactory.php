<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SupervisionNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SupervisionNote>
 */
class SupervisionNoteFactory extends Factory
{
    protected $model = SupervisionNote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'meeting_date' => fake()->date(),
            'advisor_name' => fake()->name(),
            'notes' => fake()->paragraph(),
        ];
    }
}
