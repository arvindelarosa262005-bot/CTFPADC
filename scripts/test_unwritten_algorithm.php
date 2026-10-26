<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\User;
use App\Models\VerifiedSolve;
use App\Services\FlagSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

echo "=== P4DC CTF: Test 'The Unwritten Algorithm' Miscellaneous Challenge ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $message\n";
        $passCount++;
    } else {
        echo "[FAIL] $message\n";
        $failCount++;
    }
}

// 1. Verify Challenge Record
$challenge = Challenge::where('slug', 'the-unwritten-algorithm')->first();
assertTest($challenge !== null, "Challenge 'the-unwritten-algorithm' exists in database");
assertTest($challenge?->isLive() === true, "Challenge is active and published (Live)");
assertTest($challenge?->title === 'The Unwritten Algorithm', "Challenge title is 'The Unwritten Algorithm'");
assertTest($challenge?->category === 'miscellaneous', "Category is 'miscellaneous'");
assertTest($challenge?->difficulty === 'hard', "Difficulty is 'hard'");
assertTest($challenge?->points === 100, "Points is exactly 100");
assertTest($challenge?->hasAttachment() === true, "Challenge hasAttachment is true");
assertTest($challenge?->attachment_filename === 'the_unwritten_algorithm.zip', "Attachment filename is 'the_unwritten_algorithm.zip'");
assertTest($challenge?->target_type === 'internal', "Target type is internal");

// 2. Verify Attachment File on Storage
$storagePath = Storage::disk('public')->path($challenge->attachment_path);
assertTest(file_exists($storagePath), "Attachment file exists on disk: $storagePath");
$fileSize = filesize($storagePath);
assertTest($fileSize > 2000, "Attachment package is a valid zip archive (size: $fileSize bytes)");

// 3. Inspect ZIP Contents & Ensure No Flag Leak
$zipInspectJson = shell_exec("python3 -c \"
import zipfile, json
res = {'fileNames': [], 'leaks': 0}
with zipfile.ZipFile('$storagePath', 'r') as z:
    for name in z.namelist():
        res['fileNames'].append(name)
        content = z.read(name)
        if b'p4dCTF{h1dd3n_l0g1c_r3v34l3d}' in content or b'h1dd3n_l0g1c_r3v34l3d' in content:
            res['leaks'] += 1
print(json.dumps(res))
\"");

$zipData = json_decode($zipInspectJson, true) ?: ['fileNames' => [], 'leaks' => 1];
$fileNames = $zipData['fileNames'];
assertTest($zipData['leaks'] === 0, "No files in zip leak plaintext flag or secret body");
assertTest(count($fileNames) >= 4, "Zip archive opens successfully and contains expected files");

assertTest(in_array('samples.json', $fileNames, true), "Zip contains 'samples.json'");
assertTest(in_array('algorithm_probe.py', $fileNames, true), "Zip contains 'algorithm_probe.py'");
assertTest(in_array('target_challenge.txt', $fileNames, true), "Zip contains 'target_challenge.txt'");
assertTest(in_array('solution_template.py', $fileNames, true), "Zip contains 'solution_template.py'");

// 4. Inspect Description & Hints for Leaks
assertTest(strpos($challenge->description, 'p4dCTF') === false, "Description does NOT leak flag");
assertTest(strpos($challenge->description, 'h1dd3n_l0g1c') === false, "Description does NOT leak flag keyword");
assertTest(strpos($challenge->description, '}63676_') === false, "Description does NOT leak expected output");
assertTest(count($challenge->hintList()) <= 2, "At most two optional hints provided (count: " . count($challenge->hintList()) . ")");

foreach ($challenge->hintList() as $idx => $hint) {
    assertTest(strpos($hint, 'p4dCTF') === false, "Hint " . ($idx + 1) . " does not spoil flag");
    assertTest(strpos($hint, '}63676_') === false, "Hint " . ($idx + 1) . " does not spoil output string");
    assertTest(strpos($hint, '% 3') === false, "Hint " . ($idx + 1) . " avoids spoonfeeding modulo formula");
}

// 5. Test Regular Participant Account Modal & Detail Rendering
$testUser = User::where('role', 'contestant')->first();
if (!$testUser) {
    $testUser = User::create([
        'username' => 'algo_tester_' . time(),
        'email' => 'algo_tester_' . time() . '@example.com',
        'password' => bcrypt('password123'),
        'role' => 'contestant',
        'is_active' => true,
    ]);
}

$req = Request::create('/');
$app->instance('request', $req);
Auth::login($testUser);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());

$modalHtml = view('challenges._modal', [
    'challenge' => $challenge,
    'isSolved' => false,
    'targetUrl' => $challenge->resolveTargetUrl(),
    'hints' => $challenge->hintList(),
])->render();

assertTest(strpos($modalHtml, 'the_unwritten_algorithm.zip') !== false, "Modal contains reference to 'the_unwritten_algorithm.zip'");
assertTest(strpos($modalHtml, 'Download Package') !== false, "Modal contains 'Download Package' button");
assertTest(strpos($modalHtml, 'Algorithm Dataset') !== false, "Modal contains Algorithm Dataset section");
assertTest(strpos($modalHtml, 'Open Verification Portal') !== false, "Modal contains link to Open Verification Portal");
assertTest(strpos($modalHtml, 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}') === false, "Modal HTML does NOT leak canonical flag");
assertTest(strpos($modalHtml, 'placeholder="p4dCTF{...}"') !== false, "Modal contains placeholder 'p4dCTF{...}'");

$detailHtml = view('challenges.detail', [
    'challenge' => $challenge,
    'isSolved' => false,
    'targetUrl' => $challenge->resolveTargetUrl(),
    'hints' => $challenge->hintList(),
])->render();
assertTest(strpos($detailHtml, 'Download Package') !== false, "Detail page contains 'Download Package' button");
assertTest(strpos($detailHtml, 'Open Verification Portal') !== false, "Detail page contains link to Open Verification Portal");
assertTest(strpos($detailHtml, 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}') === false, "Detail HTML does NOT leak flag");

// Target page rendering
$targetHtml = view('challenges.targets.the-unwritten-algorithm', [
    'challenge' => $challenge,
])->render();
assertTest(strpos($targetHtml, 'P4DC{c0gn1t1v3_tr4nsf0rm_v2_2026}') !== false, "Target page displays challenge sequence");
assertTest(strpos($targetHtml, 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}') === false, "Target page HTML does NOT leak flag in static template");

// 6. Test Download Controller Endpoint with Participant Account
$controller = app(\App\Http\Controllers\ChallengeController::class);
$downloadResp = $controller->download($challenge);
assertTest($downloadResp->getStatusCode() === 200, "Download controller returns HTTP 200");
$headers = $downloadResp->headers;
$contentDisposition = $headers->get('content-disposition');
assertTest(strpos($contentDisposition, 'the_unwritten_algorithm.zip') !== false, "Content-Disposition specifies 'the_unwritten_algorithm.zip'");

// 7. Test Online Target Verification Endpoint
echo "\n--- Target Portal Verification Endpoint ---\n";
$targetController = app(\App\Http\Controllers\ChallengeTargetController::class);

// Incorrect candidate output
$badReq = Request::create(route('challenges.target.verify', ['challenge' => $challenge]), 'POST', [
    'candidate_output' => 'incorrect_guess_123',
]);
$badResp = $targetController->verify($challenge, $badReq);
assertTest($badResp->getStatusCode() === 422, "Invalid candidate output returns HTTP 422");
$badData = json_decode($badResp->getContent(), true);
assertTest($badData['success'] === false, "Invalid output responds with success = false");
assertTest(!isset($badData['flag']), "Invalid output does NOT return flag");

// Correct candidate output
$correctOutput = '}63676__zAcA7Ugk1_fm0w8c84O{wQuS1';
$goodReq = Request::create(route('challenges.target.verify', ['challenge' => $challenge]), 'POST', [
    'candidate_output' => $correctOutput,
]);
$goodResp = $targetController->verify($challenge, $goodReq);
assertTest($goodResp->getStatusCode() === 200, "Valid candidate output returns HTTP 200");
$goodData = json_decode($goodResp->getContent(), true);
assertTest($goodData['success'] === true, "Valid output responds with success = true");
assertTest(isset($goodData['flag']), "Valid output returns flag");
$unlockedFlag = $goodData['flag'];
echo "Unlocked Flag from Verification Portal: '$unlockedFlag'\n";
assertTest($unlockedFlag === 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}', "Unlocked flag matches 'p4dCTF{h1dd3n_l0g1c_r3v34l3d}' exactly");

// 8. Server-Side Flag Validation & Scoring Integration
echo "\n--- Server-Side Flag Validation & Scoring ---\n";
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

$service = app(FlagSubmissionService::class);

// Test wrong flag submission
$wrongRes = $service->attempt($testUser, $challenge, 'p4dCTF{wrong_algorithm_flag}');
assertTest($wrongRes['status'] === 'incorrect', "Incorrect flag submission returns 'incorrect'");

// Test correct flag submission
$correctRes = $service->attempt($testUser, $challenge, $unlockedFlag);
assertTest($correctRes['status'] === 'solved', "Correct flag submission returns 'solved'");
assertTest($correctRes['points_awarded'] === 100, "Awarded points equals exactly 100");

// Verify DB solve record
$solveRecord = VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->first();
assertTest($solveRecord !== null && $solveRecord->points_awarded === 100, "VerifiedSolve record created with 100 points");

// Verify Participant Total Score Update
$userPoints = (int) VerifiedSolve::where('user_id', $testUser->id)->sum('points_awarded');
assertTest($userPoints >= 100, "User total verified points reflects the 100 points awarded (current sum: $userPoints)");

// Test duplicate solve prevention
$dupRes = $service->attempt($testUser, $challenge, $unlockedFlag);
assertTest($dupRes['status'] === 'already_solved', "Subsequent submission returns 'already_solved'");
assertTest($dupRes['points_awarded'] === 0, "No additional points awarded on duplicate solve");

// Clean up test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

// 9. System Integrity & Admin Account Preservation
echo "\n--- System Integrity Preservation ---\n";
$admin = User::where('username', 'admin')->first();
assertTest($admin !== null && $admin->role === 'admin' && $admin->is_active, "Primary admin account remains intact and active (ID: " . ($admin?->id ?? 'none') . ")");

$totalChallenges = Challenge::count();
assertTest($totalChallenges >= 11, "All challenges preserved (Total: $totalChallenges)");

echo "\n==========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "==========================================\n";

if ($failCount > 0) {
    exit(1);
}
