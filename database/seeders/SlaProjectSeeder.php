<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\SlaProject;
use Illuminate\Database\Seeder;

class SlaProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();

        $projects->each(function (Project $project) {
            SlaProject::factory()->create([
                'project_id' => $project->id,
            ]);
        });
    }
}
