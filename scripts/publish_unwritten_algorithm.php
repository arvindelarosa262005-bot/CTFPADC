<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Challenge;
use Illuminate\Support\Carbon;

echo "=== Publishing 'The Unwritten Algorithm' Challenge ===\n";

$slug = 'the-unwritten-algorithm';
$title = 'The Unwritten Algorithm';
$category = 'miscellaneous';
$difficulty = 'hard';
$points = 100;
$flag = 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}';
$description = 'A mysterious program produces unusual outputs for a collection of inputs. Its original documentation is missing, and the transformation rules are unknown. Analyze the provided evidence, determine the underlying logic, and reproduce the expected result to uncover the flag.';
$hints = [
    'Compare how characters at different positions shift, and pay close attention to letter casing changes on specific character types.',
    'Notice how the overall arrangement of the transformed string depends on a global property of the original input\'s character values.'
];
$attachmentPath = 'challenges/the_unwritten_algorithm.zip';
$attachmentFilename = 'the_unwritten_algorithm.zip';

$challenge = Challenge::where('slug', $slug)->first();

if (!$challenge) {
    $challenge = new Challenge();
    $challenge->slug = $slug;
    echo "Creating new challenge: $title ($slug)\n";
} else {
    echo "Updating existing challenge: $title ($slug, ID: {$challenge->id})\n";
}

$challenge->title = $title;
$challenge->category = $category;
$challenge->difficulty = $difficulty;
$challenge->points = $points;
$challenge->flag = $flag;
$challenge->description = $description;
$challenge->hints = $hints;
$challenge->attachment_path = $attachmentPath;
$challenge->attachment_filename = $attachmentFilename;
$challenge->target_type = 'internal';
$challenge->is_active = true;
$challenge->published_at = Carbon::now()->subMinute();
$challenge->save();

echo "[+] Successfully published challenge ID: {$challenge->id}\n";
echo "    Title: {$challenge->title}\n";
echo "    Slug: {$challenge->slug}\n";
echo "    Category: {$challenge->category}\n";
echo "    Difficulty: {$challenge->difficulty}\n";
echo "    Points: {$challenge->points}\n";
echo "    Attachment: {$challenge->attachment_path} ({$challenge->attachment_filename})\n";
echo "    Active: " . ($challenge->is_active ? 'YES' : 'NO') . "\n";
echo "    Published: " . ($challenge->isPublished() ? 'YES' : 'NO') . "\n";

