<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$challenge = App\Models\Challenge::where('slug', 'rsa-oracle')->first();
if (!$challenge) {
    echo "ERROR: Challenge rsa-oracle not found!\n";
    exit(1);
}
echo "Challenge found: ID={$challenge->id}, Slug={$challenge->slug}, Type={$challenge->target_type}\n";

$users = App\Models\User::where('role', 'contestant')->get();
$user = $users[0];
echo "Testing with contestant: {$user->username} (ID: {$user->id})\n";

// Test 1: Test target route controller
$request = Illuminate\Http\Request::create('/challenges/rsa-oracle/target', 'GET');
$app->instance('request', $request);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());
auth()->login($user);

$targetCtrl = app(App\Http\Controllers\ChallengeTargetController::class);
$targetResponse = $targetCtrl->show($challenge);
$targetHtml = $targetResponse->render();

echo "\n--- Target Route Checks ---\n";
assert(strpos($targetHtml, 'P4DC Central Banking Gateway') !== false, 'Target title missing!');
assert(preg_match('/nc\s+.*5000/', $targetHtml) === 1, 'Connection command missing!');
assert(strpos($targetHtml, 'password.enc') !== false, 'password.enc link missing!');
assert(strpos($targetHtml, 'secret.enc') !== false, 'secret.enc link missing!');
echo "Check 1: Target view renders successfully with HTTP 200 content (PASSED)\n";

// Test 2: Test challenge modal rendering
$challengeCtrl = app(App\Http\Controllers\ChallengeController::class);
$modalRequest = Illuminate\Http\Request::create('/challenges/rsa-oracle', 'GET');
$modalResponse = $challengeCtrl->show($challenge, $modalRequest);
$modalHtml = $modalResponse->render();

echo "\n--- Modal Checks ---\n";
assert(preg_match('/nc\s+.*5000/', $modalHtml) === 1, 'Modal connection command missing!');
assert(strpos($modalHtml, 'password.enc') !== false, 'Modal password.enc link missing!');
assert(strpos($modalHtml, 'secret.enc') !== false, 'Modal secret.enc link missing!');
assert(strpos($modalHtml, 'challenges/rsa-oracle/target') !== false, 'Modal target link missing!');
echo "Check 2: Modal view renders with artifacts and target link (PASSED)\n";

// Test 3: Flag submission
$flagService = app(App\Services\FlagSubmissionService::class);

// Clean up any test solve
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();

// Incorrect flag
$resWrong = $flagService->attempt($user, $challenge, 'p4dCTF{wrong_flag_test}');
echo "\n--- Submission Checks ---\n";
echo "Wrong flag: " . json_encode($resWrong) . "\n";
assert($resWrong['status'] === 'incorrect');

// Correct flag
$correctFlag = 'p4dCTF{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}';
$resCorrect = $flagService->attempt($user, $challenge, $correctFlag);
echo "Correct flag: " . json_encode($resCorrect) . "\n";
assert($resCorrect['status'] === 'solved' && $resCorrect['points_awarded'] === (int) $challenge->points);

// Duplicate submission
$resDup = $flagService->attempt($user, $challenge, $correctFlag);
echo "Duplicate submission: " . json_encode($resDup) . "\n";
assert($resDup['status'] === 'already_solved');

// Clean up
App\Models\VerifiedSolve::where('challenge_id', $challenge->id)->delete();
App\Models\ChallengeSubmission::where('challenge_id', $challenge->id)->delete();
echo "Check 3: Flag submission & scoring verified, test solves cleaned (PASSED)\n";

echo "\nALL RSA CHALLENGE TESTS PASSED SUCCESSFULLY!\n";

