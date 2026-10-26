<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Challenge;

$challenge = Challenge::updateOrCreate(
    ['slug' => 'hidden-in-the-hounds'],
    [
        'title' => 'Hidden in the Hounds',
        'category' => 'steganography',
        'difficulty' => 'medium',
        'points' => 50,
        'target_type' => 'internal',
        'target_url' => null,
        'description' => 'An ordinary dog photograph has entered the evidence archive. Nothing unusual appears at first glance, but appearances can be misleading. Download the image and investigate it carefully to uncover the hidden flag.',
        'hints' => [
            "Visual inspection won't reveal altered pixels. Focus your analysis on the least significant components of the image data.",
            "Digital images store color values across color channels. Consider how binary data can be concealed across sequential pixel bytes.",
            "Standard steganography tools examine bit planes sequentially (RGB, bit 0) to reconstruct hidden payloads.",
        ],
        'attachment_path' => 'challenges/popular_dog_breeds.png',
        'attachment_filename' => 'popular_dog_breeds.png',
        'is_active' => true,
        'published_at' => now()->subHour(),
        'flag' => 'p4dCTF{hidden_b3tw33n_p1x3ls}',
    ]
);

echo "Challenge created/updated successfully!\n";
echo "ID: {$challenge->id}\n";
echo "Title: {$challenge->title}\n";
echo "Slug: {$challenge->slug}\n";
echo "Category: {$challenge->category}\n";
echo "Difficulty: {$challenge->difficulty}\n";
echo "Points: {$challenge->points}\n";
echo "Is Live: " . ($challenge->isLive() ? 'YES' : 'NO') . "\n";
echo "Attachment: {$challenge->attachment_path} ({$challenge->downloadFilename()})\n";
echo "Has Attachment: " . ($challenge->hasAttachment() ? 'YES' : 'NO') . "\n";
echo "Hints Count: " . count($challenge->hintList()) . "\n";

