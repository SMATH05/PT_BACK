<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SlaProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaProject>
 */
class SlaProjectFactory extends Factory
{
    protected $model = SlaProject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                => fake()->words(3, true) . ' SLA',
            'max_response_time'   => fake()->numberBetween(30, 480),   // in minutes
            'max_resolution_time' => fake()->numberBetween(480, 4320), // in minutes
            'priority'            => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'project_id'          => Project::factory(),
        ];
    }
}
