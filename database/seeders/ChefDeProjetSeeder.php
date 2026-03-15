<?php

namespace Database\Seeders;

use App\Models\ChefDeProjet;
use App\Models\Manager;
use Illuminate\Database\Seeder;

class ChefDeProjetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $managers = Manager::all();

        $managers->each(function (Manager $manager) {
            ChefDeProjet::factory()->count(2)->create([
                'manager_id' => $manager->id,
            ]);
        });
    }
}
