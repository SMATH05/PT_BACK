<?php

namespace Database\Seeders;

use App\Models\SlaTask;
use App\Models\Task;
use Illuminate\Database\Seeder;

class SlaTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tasks = Task::all();

        $tasks->each(function (Task $task) {
            SlaTask::factory()->create([
                'task_id' => $task->id,
            ]);
        });
    }
}
