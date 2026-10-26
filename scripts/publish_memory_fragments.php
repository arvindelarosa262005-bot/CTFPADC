<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Challenge;
use Illuminate\Support\Carbon;

echo "=== Publishing 'Memory Fragments' Challenge ===\n";

$slug = 'memory-fragments';
$title = 'Memory Fragments';
$category = 'miscellaneous';
$difficulty = 'medium';
$points = 60;
$flag = 'p4dCTF{fr4gm3nts_0f_m3m0ry}';
$description = 'A synthetic memory capture was recovered during an investigation of a suspicious system process. The dump contains process artifacts, event logs, and memory residue. Analyze the evidence to identify the relevant process data and recover the flag.';
$hints = [
    'Inspect the system logs and termination status to understand the context and purpose of the crashed process.',
    'Look beyond superficial text strings—examine the memory structures associated with the active worker.'
];
$attachmentPath = 'challenges/memory_dump.bin';
$attachmentFilename = 'memory_dump.bin';

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
$challenge->is_active = true;
$challenge->target_type = 'internal';
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

