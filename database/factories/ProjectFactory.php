<?php

namespace Database\Factories;

use App\Models\ChefDeProjet;
use App\Models\Manager;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => fake()->sentence(3),
            'client'            => fake()->company(),
            'description'       => fake()->optional()->paragraph(),
            'start_date'        => fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'end_date'          => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'deadline'          => fake()->dateTimeBetween('now', '+1 year')->format('Y-m-d'),
            'status'            => fake()->randomElement(['pending', 'in_progress', 'done']),
            'manager_id'        => Manager::factory(),
            'chef_de_projet_id' => ChefDeProjet::factory(),
        ];
    }
}
