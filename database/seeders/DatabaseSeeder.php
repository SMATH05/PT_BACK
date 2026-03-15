<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: parent tables must be seeded before child tables.
     */
    public function run(): void
    {
        $this->call([
            ManagerSeeder::class,
            ChefDeProjetSeeder::class,
            DeveloperSeeder::class,
            ProjectSeeder::class,
            TaskSeeder::class,
            SlaProjectSeeder::class,
            SlaTaskSeeder::class,
            DeveloperProjectSeeder::class,
            DeveloperTaskSeeder::class,
        ]);
    }
}
