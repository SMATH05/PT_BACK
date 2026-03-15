<?php

namespace Database\Factories;

use App\Models\ChefDeProjet;
use App\Models\Manager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChefDeProjet>
 */
class ChefDeProjetFactory extends Factory
{
    protected $model = ChefDeProjet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => fake()->name(),
            'email'      => fake()->unique()->safeEmail(),
            'manager_id' => Manager::factory(),
        ];
    }
}
