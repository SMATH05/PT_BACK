<?php

namespace Database\Factories;

use App\Models\SlaTask;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlaTask>
 */
class SlaTaskFactory extends Factory
{
    protected $model = SlaTask::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                => fake()->words(3, true) . ' SLA',
            'max_response_time'   => fake()->numberBetween(15, 240),   // in minutes
            'max_resolution_time' => fake()->numberBetween(60, 1440),  // in minutes
            'priority'            => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'task_id'             => Task::factory(),
        ];
    }
}
