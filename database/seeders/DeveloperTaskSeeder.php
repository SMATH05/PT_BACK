<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Models\Task;
use Illuminate\Database\Seeder;

class DeveloperTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $developers = Developer::all();
        $tasks      = Task::all();
        $roles      = ['developer', 'reviewer', 'tester', 'lead'];

        // Assign each developer to 2–5 random tasks
        $developers->each(function (Developer $developer) use ($tasks, $roles) {
            $randomTasks = $tasks->random(min(rand(2, 5), $tasks->count()));

            foreach ($randomTasks as $task) {
                $developer->tasks()->attach($task->id, [
                    'role'        => $roles[array_rand($roles)],
                    'assigned_at' => now()->subDays(rand(1, 60)),
                ]);
            }
        });
    }
}
