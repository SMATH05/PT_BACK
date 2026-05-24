<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProjectFile>
 */
class ProjectFileFactory extends Factory
{
    protected $model = ProjectFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->fileExtension() === 'pdf' 
            ? fake()->word() . '.pdf'
            : fake()->word() . '.' . fake()->fileExtension();

        return [
            'project_id' => Project::factory(),
            'filename'   => $filename,
            'filepath'   => 'projects/' . fake()->uuid() . '/' . $filename,
            'disk'       => 'local',
            'mime_type'  => fake()->mimeType(),
            'size'       => fake()->numberBetween(1024, 10485760), // 1KB to 10MB
        ];
    }
}
