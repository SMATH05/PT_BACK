<?php

namespace Database\Seeders;

use App\Models\ChefDeProjet;
use App\Models\Manager;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $managers      = Manager::all();
        $chefDeProjets = ChefDeProjet::all();

        $managers->each(function (Manager $manager) use ($chefDeProjets) {
            // Each manager creates 2 projects, supervised by a random chef de projet
            Project::factory()->count(2)->create([
                'manager_id'        => $manager->id,
                'chef_de_projet_id' => $chefDeProjets->random()->id,
            ]);
        });
    }
}
