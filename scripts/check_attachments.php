<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (App\Models\Challenge::all() as $ch) {
    if ($ch->attachment_path) {
        $path = Illuminate\Support\Facades\Storage::disk('public')->path($ch->attachment_path);
        echo $ch->slug . " -> DB: " . $ch->attachment_path . " -> Path: " . $path . " -> is_file: " . (is_file($path) ? 'YES' : 'NO') . PHP_EOL;
    }
}

