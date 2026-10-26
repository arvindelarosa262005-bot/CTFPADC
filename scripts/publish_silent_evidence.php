<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Challenge;

$challenge = Challenge::updateOrCreate(
    ['slug' => 'silent-evidence'],
    [
        'title' => 'Silent Evidence',
        'category' => 'steganography',
        'difficulty' => 'medium',
        'points' => 50,
        'target_type' => 'internal',
        'target_url' => null,
        'description' => 'An image recovered during a digital investigation appears ordinary. Investigators suspect it contains evidence that cannot be discovered through visual inspection alone. Examine the provided artifact and recover the concealed information.',
        'hints' => [
            "The file's visual layer appears unaltered. Consider that the lowest-order components of color channels can store binary data without perceptible distortion.",
            "Digital evidence often conceals messages sequentially through fundamental pixel color channels. Analyzing channel bit planes may reveal structured text.",
            "Look for standard flag framing patterns in the reconstructed bitstream starting from the beginning of the pixel sequence.",
        ],
        'attachment_path' => 'challenges/silent_evidence.png',
        'attachment_filename' => 'silent_evidence.png',
        'is_active' => true,
        'published_at' => now()->subHour(),
        'flag' => 'p4dCTF{sh4d0ws_h1d3_s3cr3ts}',
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

