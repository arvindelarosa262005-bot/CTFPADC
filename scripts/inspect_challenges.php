<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$challenges = App\Models\Challenge::all();
foreach ($challenges as $c) {
    echo "ID: {$c->id}\n";
    echo "Slug: {$c->slug}\n";
    echo "Title: {$c->title}\n";
    echo "Category: {$c->category}\n";
    echo "Difficulty: {$c->difficulty}\n";
    echo "Points: {$c->points}\n";
    echo "Target Type: {$c->target_type}\n";
    echo "Target URL: {$c->target_url}\n";
    echo "Attachment Path: {$c->attachment_path}\n";
    echo "Attachment Filename: {$c->attachment_filename}\n";
    echo "Is Active: " . ($c->is_active ? 'true' : 'false') . "\n";
    echo "Published At: {$c->published_at}\n";
    echo "Hints: " . json_encode($c->hints) . "\n";
    echo "Description: {$c->description}\n";
    echo "Flag: {$c->flag}\n";
    echo "--------------------------------------------------\n";
}

