<?php

namespace Database\Factories;

use App\Models\ChefDeProjet;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'             => fake()->sentence(4),
            'goal'              => fake()->paragraph(),
            'status'            => fake()->randomElement(['pending', 'in_progress', 'completed', 'validated']),
            'project_id'        => Project::factory(),
            'chef_de_projet_id' => ChefDeProjet::factory(),
        ];
    }
}
