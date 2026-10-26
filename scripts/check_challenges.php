<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$challenges = App\Models\Challenge::all();
foreach ($challenges as $c) {
    echo "ID: {$c->id} | Slug: {$c->slug} | Title: {$c->title} | Flag: {$c->flag}\n";
    echo "  Category: {$c->category} | Points: {$c->points} | Type: {$c->target_type}\n";
    echo "  Target URL: {$c->target_url}\n";
    echo "  Attachment: {$c->attachment_path} ({$c->attachment_filename})\n";
    echo "  Hints: " . json_encode($c->hints) . "\n";
    echo "  Description: {$c->description}\n\n";
}

