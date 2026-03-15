<?php

namespace Database\Seeders;

use App\Models\Developer;
use App\Models\Manager;
use Illuminate\Database\Seeder;

class DeveloperSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $managers = Manager::all();

        $managers->each(function (Manager $manager) {
            Developer::factory()->count(4)->create([
                'manager_id' => $manager->id,
            ]);
        });
    }
}
