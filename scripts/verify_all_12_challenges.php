<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::firstOrCreate(
    ['username' => 'testsolver_verify'],
    ['email' => 'verifier@test.com', 'password' => 'secret123', 'is_active' => true]
);

$service = app(\App\Services\FlagSubmissionService::class);

echo "Verifying all 12 CTF challenges:\n\n";

$allChallenges = \App\Models\Challenge::orderBy('id')->get();
$passed = 0;

foreach ($allChallenges as $c) {
    // Temporarily ensure challenge is active for test
    $c->update(['is_active' => true, 'published_at' => now()->subMinute()]);
    
    // Clear previous solves for test user
    \App\Models\VerifiedSolve::where('user_id', $user->id)->where('challenge_id', $c->id)->delete();
    
    $flag = $c->flag;
    $res = $service->attempt($user, $c, $flag);
    
    $hasTargetView = view()->exists("challenges.targets.{$c->slug}");
    $hasAttachment = $c->hasAttachment();
    $attachmentExists = $hasAttachment && file_exists(storage_path('app/public/' . $c->attachment_path));
    
    echo sprintf(
        "[%s] %s (Pts: %d)\n  - Flag: %s\n  - Submit Result: %s (Points: %d)\n  - Has Target View: %s\n  - Has Attachment: %s (Exists on disk: %s)\n\n",
        $c->slug,
        $c->title,
        $c->points,
        $flag,
        $res['status'],
        $res['points_awarded'],
        $hasTargetView ? 'YES' : 'NO',
        $hasAttachment ? $c->attachment_path : 'NONE',
        $hasAttachment ? ($attachmentExists ? 'YES' : 'MISSING!') : 'N/A'
    );
    
    if (in_array($res['status'], ['first_blood', 'solved'])) {
        $passed++;
    }
}

echo "RESULT: $passed / " . count($allChallenges) . " challenges successfully solved and verified!\n";

// Cleanup test user and solves
\App\Models\VerifiedSolve::where('user_id', $user->id)->delete();
$user->delete();

// Restore starter challenge state (only source-trail is active by default, others staged)
foreach ($allChallenges as $c) {
    $isActive = ($c->slug === 'source-trail');
    $c->update(['is_active' => $isActive, 'published_at' => $isActive ? now() : null]);
}
echo "Database state restored cleanly.\n";
