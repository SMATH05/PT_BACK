<?php

namespace Database\Seeders;

use App\Models\Manager;
use Illuminate\Database\Seeder;

class ManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the specific manager "roda benlaama"
        Manager::create([
            'name'  => 'roda benlaama',
            'email' => 'rbenlaama2005@gmail.com',
        ]);

        // Create 3 additional random managers
        Manager::factory()->count(3)->create();
    }
}
