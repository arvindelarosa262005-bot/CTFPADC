<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$challenge = App\Models\Challenge::where('slug', 'hidden-in-plain-sight')->first();
$users = App\Models\User::where('role', 'contestant')->get();
$user1 = $users[0];
$user2 = $users->count() > 1 ? $users[1] : null;

// Clean up any test solve from user1 for challenge 6 so we can retest
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();

$flagService = app(App\Services\FlagSubmissionService::class);

// Test 1: canonical flag p4dCTF{lsb_simple_hidden_flag}
$res1 = $flagService->attempt($user1, $challenge, 'p4dCTF{lsb_simple_hidden_flag}');
echo "p4dCTF flag submission: " . json_encode($res1) . "\n";
assert($res1['status'] === 'solved' && $res1['points_awarded'] === 30);

// Test 2: alternate flag P4DC{lsb_simple_hidden_flag}
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();
$res2 = $flagService->attempt($user1, $challenge, 'P4DC{lsb_simple_hidden_flag}');
echo "Alternate flag submission: " . json_encode($res2) . "\n";
assert($res2['status'] === 'solved' && $res2['points_awarded'] === 30);

// Clean up test solves so participant data remains clean
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();
echo "Cleaned up test submissions.\n";

