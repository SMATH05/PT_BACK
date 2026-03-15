<?php

namespace Database\Seeders;

use App\Models\ChefDeProjet;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects      = Project::all();
        $chefDeProjets = ChefDeProjet::all();

        $projects->each(function (Project $project) use ($chefDeProjets) {
            Task::factory()->count(5)->create([
                'project_id'        => $project->id,
                'chef_de_projet_id' => $chefDeProjets->random()->id,
            ]);
        });
    }
}
