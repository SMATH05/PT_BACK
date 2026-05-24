<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ChefDeProjet;
use App\Models\Manager;

$manager = Manager::first();
$managerId = $manager ? $manager->id : 1;

ChefDeProjet::firstOrCreate(
    ['email' => 'aymanejanati@gmail.com'],
    [
        'name' => 'aymane janati',
        'manager_id' => $managerId
    ]
);

echo "Fixed existing user's role in DB.\n";
