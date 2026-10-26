<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$c = App\Models\Challenge::where('slug', 'hidden-in-plain-sight')->first();
if ($c) {
    $c->flag = 'p4dCTF{lsb_simple_hidden_flag}';
    $c->points = 30;
    $c->save();
    echo "Updated challenge 6 canonical flag to: " . $c->flag . " and points to: " . $c->points . "\n";
} else {
    echo "Challenge 6 not found!\n";
}
