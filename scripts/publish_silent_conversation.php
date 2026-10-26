<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Challenge;

$challenge = Challenge::updateOrCreate(
    ['slug' => 'the-silent-conversation'],
    [
        'title' => 'The Silent Conversation',
        'category' => 'networking',
        'difficulty' => 'easy',
        'points' => 60,
        'target_type' => 'internal',
        'target_url' => null,
        'description' => 'A routine network capture contains one conversation that stands out from the rest. Investigate the traffic and recover the information exchanged between the endpoints.',
        'hints' => [
            "Network captures often bundle multiple concurrent communication flows. Analyzing the protocol hierarchy or conversation list can help isolate unusual sessions.",
            "Look closely at connections outside ordinary web and infrastructure traffic. Reconstructing the full exchange between endpoints may reveal hidden communication.",
            "Inspect the raw data payloads exchanged between client and server across suspicious transmission streams.",
        ],
        'attachment_path' => 'challenges/the_silent_conversation.pcap',
        'attachment_filename' => 'the_silent_conversation.pcap',
        'is_active' => true,
        'published_at' => now()->subHour(),
        'flag' => 'p4dCTF{tcp_str34m_r3c0v3r3d}',
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

