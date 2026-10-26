<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$challenge = App\Models\Challenge::where('slug', 'hidden-secrets')->first();
if (!$challenge) {
    echo "ERROR: Challenge hidden-secrets not found!\n";
    exit(1);
}

echo "Challenge found: ID={$challenge->id}, Title={$challenge->title}, Points={$challenge->points}\n";

$users = App\Models\User::where('role', 'contestant')->get();
if ($users->isEmpty()) {
    echo "ERROR: No contestant users found!\n";
    exit(1);
}

$user = $users[0];
echo "Testing with contestant: {$user->username} (ID: {$user->id})\n";

$flagService = app(App\Services\FlagSubmissionService::class);

// Clean up any prior test solves for challenge 5
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();

// Test 1: Wrong flag
$resWrong = $flagService->attempt($user, $challenge, 'P4dCTF{wrong_attempt_123}');
echo "Test 1 (Wrong flag): " . json_encode($resWrong) . "\n";
assert($resWrong['status'] === 'incorrect');

// Test 2: Submitted flag from artifact: P4dCTF{strings_r3v34l_s3cr3ts}
$res1 = $flagService->attempt($user, $challenge, 'P4dCTF{strings_r3v34l_s3cr3ts}');
echo "Test 2 (P4dCTF flag): " . json_encode($res1) . "\n";
assert($res1['status'] === 'solved' && $res1['points_awarded'] === 50);

// Test 3: Already solved check
$resDup = $flagService->attempt($user, $challenge, 'P4dCTF{strings_r3v34l_s3cr3ts}');
echo "Test 3 (Duplicate submission): " . json_encode($resDup) . "\n";
assert($resDup['status'] === 'already_solved');

// Clean up first solve
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();

// Test 4: Lowercase flag: p4dCTF{strings_r3v34l_s3cr3ts}
$res2 = $flagService->attempt($user, $challenge, 'p4dCTF{strings_r3v34l_s3cr3ts}');
echo "Test 4 (p4dCTF lowercase): " . json_encode($res2) . "\n";
assert($res2['status'] === 'solved' && $res2['points_awarded'] === 50);

// Test 5: P4DC format: P4DC{strings_r3v34l_s3cr3ts}
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();
$res3 = $flagService->attempt($user, $challenge, 'P4DC{strings_r3v34l_s3cr3ts}');
echo "Test 5 (P4DC format): " . json_encode($res3) . "\n";
assert($res3['status'] === 'solved' && $res3['points_awarded'] === 50);

// Clean up after all tests so database is pristine
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();

// Test 6: Verify view rendering has no exposed flag and correct structure
$request = Illuminate\Http\Request::create('/challenges/hidden-secrets', 'GET');
$app->instance('request', $request);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());
auth()->login($user);
$controller = app(App\Http\Controllers\ChallengeController::class);
$response = $controller->show($challenge, $request);
$html = $response->render();

echo "\n--- UI Rendering Checks ---\n";
// 1. Verify flag is not exposed in HTML
if (stripos($html, 'strings_r3v34l_s3cr3ts') !== false) {
    echo "ERROR: Flag is exposed in rendered challenge HTML!\n";
    exit(1);
}
echo "Check 1: Flag is NOT exposed in challenge page/modal HTML (PASSED)\n";

// 2. Verify evidence download links
if (strpos($html, 'memory_capture.bin') === false || strpos($html, 'incident_report.log') === false || strpos($html, 'evidence_notes.txt') === false) {
    echo "ERROR: Evidence download links are missing in HTML!\n";
    exit(1);
}
echo "Check 2: Evidence download links are present for all 3 artifacts (PASSED)\n";

// 3. Verify hints are collapsed (<details> without 'open')
if (strpos($html, '<details class="modal-hint-item">') === false) {
    echo "ERROR: Hints not rendered in collapsed <details> elements!\n";
    exit(1);
}
if (strpos($html, '<details class="modal-hint-item" open>') !== false) {
    echo "ERROR: Hints must NOT be open by default!\n";
    exit(1);
}
echo "Check 3: Hints are collapsed by default in <details> elements (PASSED)\n";

// 4. Verify floating modal structure and submission form
if (strpos($html, 'challenge-detail-modal') === false || strpos($html, 'data-modal-flag-form') === false) {
    echo "ERROR: Modal structure or submission form missing!\n";
    exit(1);
}
echo "Check 4: Floating modal and AJAX submission form present (PASSED)\n";

echo "\nALL TESTS AND CHECKS COMPLETED SUCCESSFULLY!\n";
