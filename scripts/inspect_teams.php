<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;

$teams = Team::all();
echo "Total teams: " . $teams->count() . "\n";
foreach ($teams as $t) {
    echo "ID: {$t->id} | Name: {$t->name} | Code: {$t->invitation_code_encrypted} | Status: {$t->status}\n";
}

