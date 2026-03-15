<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;

class DeveloperProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $developers = Developer::all();
        $projects   = Project::all();
        $positions  = ['frontend', 'backend', 'fullstack', 'devops', 'qa'];

        // Assign each developer to 1–3 random projects
        $developers->each(function (Developer $developer) use ($projects, $positions) {
            $randomProjects = $projects->random(min(rand(1, 3), $projects->count()));

            foreach ($randomProjects as $project) {
                $developer->projects()->attach($project->id, [
                    'position'  => $positions[array_rand($positions)],
                    'joined_at' => now()->subDays(rand(1, 90)),
                ]);
            }
        });
    }
}
