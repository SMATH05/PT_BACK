<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Seeder;

class ProjectFileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $projects = Project::all();

        // Each project gets 1-5 random files
        $projects->each(function (Project $project) {
            $fileCount = rand(1, 5);
            ProjectFile::factory()->count($fileCount)->create([
                'project_id' => $project->id,
            ]);
        });
    }
}
