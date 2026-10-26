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

echo "=== P4DC CTF: Test 'Memory Fragments' Digital Forensics Challenge ===\n\n";

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
$challenge = Challenge::where('slug', 'memory-fragments')->first();
assertTest($challenge !== null, "Challenge 'memory-fragments' exists in database");
assertTest($challenge?->isLive() === true, "Challenge is active and published (Live)");
assertTest($challenge?->title === 'Memory Fragments', "Challenge title is 'Memory Fragments'");
assertTest($challenge?->category === 'miscellaneous', "Category is 'miscellaneous'");
assertTest($challenge?->difficulty === 'medium', "Difficulty is 'medium'");
assertTest($challenge?->points === 60, "Points is 60");
assertTest($challenge?->hasAttachment() === true, "Challenge hasAttachment is true");
assertTest($challenge?->attachment_filename === 'memory_dump.bin', "Attachment filename is 'memory_dump.bin'");

// 2. Verify Attachment File on Storage
$storagePath = Storage::disk('public')->path($challenge->attachment_path);
assertTest(file_exists($storagePath), "Attachment file exists on disk: $storagePath");
$fileSize = filesize($storagePath);
assertTest($fileSize === 65536, "Attachment file size is genuine 64KB memory dump ($fileSize bytes)");

// 3. Inspect Binary Header (64-bit ELF Core Dump)
$rawContents = file_get_contents($storagePath);
$magic = substr($rawContents, 0, 4);
assertTest($magic === "\x7fELF", "File has valid ELF magic header (\\x7fELF)");
$eiClass = ord($rawContents[4]);
$eiData = ord($rawContents[5]);
assertTest($eiClass === 2, "ELF class is 64-bit (EI_CLASS=2)");
assertTest($eiData === 1, "ELF data encoding is 2's complement, little endian (EI_DATA=1)");

$eType = unpack('v', substr($rawContents, 16, 2))[1];
$eMachine = unpack('v', substr($rawContents, 18, 2))[1];
assertTest($eType === 4, "ELF type is ET_CORE (Core Dump, type=4)");
assertTest($eMachine === 62, "ELF machine is EM_X86_64 (Advanced Micro Devices X86-64, machine=62)");

// 4. Verify Complete Flag & Overly Revealing Notes are NOT in Memory Dump
assertTest(strpos($rawContents, 'p4dCTF{fr4gm3nts_0f_m3m0ry}') === false, "Complete plaintext flag is NOT plainly visible in raw memory dump");
assertTest(strpos($rawContents, 'fr4gm3nts_0f_m3m0ry') === false, "Full flag inner keyword is NOT plainly visible in raw memory dump");
assertTest(strpos($rawContents, 'BACKUP_NOTE') === false, "Memory dump does NOT contain artificial spoonfeeding BACKUP_NOTE");
assertTest(strpos($rawContents, 'DECOY_NOTICE') === false, "Memory dump does NOT contain artificial DECOY_NOTICE");
assertTest(strpos($rawContents, 'header tag: [MEMFRAG]') === false, "Memory dump logs do NOT spoonfeed [MEMFRAG] tag");

// 5. Inspect Description & Hints for Leaks and Over-Disclosure
assertTest(strpos($challenge->description, 'p4dCTF') === false, "Description does NOT leak flag");
assertTest(strpos($challenge->description, 'fr4gm3nts') === false, "Description does NOT leak flag keyword");
assertTest(strpos($challenge->description, 'MEMFRAG') === false, "Description does NOT spoonfeed memory tag");
assertTest(count($challenge->hintList()) <= 2, "At most two optional hints provided (count: " . count($challenge->hintList()) . ")");

foreach ($challenge->hintList() as $idx => $hint) {
    assertTest(strpos($hint, 'p4dCTF') === false, "Hint " . ($idx + 1) . " does not spoil flag");
    assertTest(strpos($hint, 'cDRkQ1RG') === false, "Hint " . ($idx + 1) . " does not spoil base64 fragment");
    assertTest(strpos($hint, '0x') === false, "Hint " . ($idx + 1) . " avoids spoonfeeding memory offsets");
    assertTest(stripos($hint, 'MEMFRAG') === false, "Hint " . ($idx + 1) . " does NOT reveal MEMFRAG marker");
    assertTest(stripos($hint, 'sequential') === false, "Hint " . ($idx + 1) . " does NOT reveal sequential reconstruction");
}

// 6. Test Regular Participant Account Modal & Detail Rendering
$testUser = User::where('role', 'contestant')->first();
if (!$testUser) {
    $testUser = User::create([
        'username' => 'forensics_tester_' . time(),
        'email' => 'forensics_tester_' . time() . '@example.com',
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

assertTest(strpos($modalHtml, 'memory_dump.bin') !== false, "Modal contains reference to 'memory_dump.bin'");
assertTest(strpos($modalHtml, 'Download Evidence') !== false, "Modal contains 'Download Evidence' button");
assertTest(strpos($modalHtml, 'Memory Dump Evidence') !== false, "Modal contains Memory Dump Evidence section");
assertTest(strpos($modalHtml, 'p4dCTF{fr4gm3nts_0f_m3m0ry}') === false, "Modal HTML does NOT leak canonical flag");
assertTest(strpos($modalHtml, 'cDRkQ1RG') === false, "Modal HTML does NOT leak encoded fragments");
assertTest(strpos($modalHtml, 'placeholder="p4dCTF{...}"') !== false, "Modal contains placeholder 'p4dCTF{...}'");

$detailHtml = view('challenges.detail', [
    'challenge' => $challenge,
    'isSolved' => false,
    'targetUrl' => $challenge->resolveTargetUrl(),
    'hints' => $challenge->hintList(),
])->render();
assertTest(strpos($detailHtml, 'Download Evidence') !== false, "Detail page contains 'Download Evidence' button");
assertTest(strpos($detailHtml, 'p4dCTF{fr4gm3nts_0f_m3m0ry}') === false, "Detail HTML does NOT leak flag");

// 7. Test Download Controller Endpoint with Participant Account
$controller = app(\App\Http\Controllers\ChallengeController::class);
$downloadResp = $controller->download($challenge);
assertTest($downloadResp->getStatusCode() === 200, "Download controller returns HTTP 200");
$headers = $downloadResp->headers;
$contentDisposition = $headers->get('content-disposition');
assertTest(strpos($contentDisposition, 'memory_dump.bin') !== false, "Content-Disposition specifies 'memory_dump.bin'");

// 8. Independent Forensic Analysis & Flag Reconstruction
echo "\n--- Independent Forensic Analysis & Flag Reconstruction ---\n";
// Extract all MEMFRAG markers
preg_match_all('/\[MEMFRAG:PART_(\d+):INDEX_(\d+)\/(\d+):([a-zA-Z0-9+\/=]+)\]/', $rawContents, $matches, PREG_SET_ORDER);
assertTest(count($matches) === 3, "Identified exactly 3 structured MEMFRAG segments in dump");

// Sort by index
usort($matches, fn($a, $b) => (int)$a[2] <=> (int)$b[2]);

$reconstructedFlag = '';
foreach ($matches as $m) {
    $idx = $m[2];
    $b64 = $m[4];
    $decoded = base64_decode($b64);
    echo "  Fragment $idx/3 ($b64) -> '$decoded'\n";
    $reconstructedFlag .= $decoded;
}

echo "Reconstructed Flag from Evidence: '$reconstructedFlag'\n";
assertTest($reconstructedFlag === 'p4dCTF{fr4gm3nts_0f_m3m0ry}', "Reconstructed flag matches 'p4dCTF{fr4gm3nts_0f_m3m0ry}' exactly");

// 9. Server-Side Flag Validation & Scoring Integration
echo "\n--- Server-Side Flag Validation & Scoring ---\n";
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

$service = app(FlagSubmissionService::class);

// Test wrong flag submission
$wrongRes = $service->attempt($testUser, $challenge, 'p4dCTF{wrong_memory_guess}');
assertTest($wrongRes['status'] === 'incorrect', "Incorrect flag submission returns 'incorrect'");

// Test decoy flag submission
$decoyRes = $service->attempt($testUser, $challenge, 'p4dCTF{f4k3_m3m0ry_fl4g_d0_n0t_subm1t}');
assertTest($decoyRes['status'] === 'incorrect', "Decoy flag submission is correctly rejected");

// Test correct flag submission
$correctRes = $service->attempt($testUser, $challenge, $reconstructedFlag);
assertTest($correctRes['status'] === 'solved', "Correct flag submission returns 'solved'");
assertTest($correctRes['points_awarded'] === 60, "Awarded points equals 60");

// Verify DB solve record
$solveRecord = VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->first();
assertTest($solveRecord !== null && $solveRecord->points_awarded === 60, "VerifiedSolve record created with 60 points");

// Verify Participant Total Score Update
$userPoints = (int) VerifiedSolve::where('user_id', $testUser->id)->sum('points_awarded');
assertTest($userPoints >= 60, "User total verified points reflects the 60 points awarded (current sum: $userPoints)");

// Test duplicate solve prevention
$dupRes = $service->attempt($testUser, $challenge, $reconstructedFlag);
assertTest($dupRes['status'] === 'already_solved', "Subsequent submission returns 'already_solved'");
assertTest($dupRes['points_awarded'] === 0, "No additional points awarded on duplicate solve");

// Clean up test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

// 10. System Integrity & Admin Account Preservation
echo "\n--- System Integrity Preservation ---\n";
$admin = User::where('username', 'admin')->first();
assertTest($admin !== null && $admin->role === 'admin' && $admin->is_active, "Primary admin account remains intact and active (ID: " . ($admin?->id ?? 'none') . ")");

$totalChallenges = Challenge::count();
assertTest($totalChallenges >= 10, "All challenges preserved (Total: $totalChallenges)");

echo "\n==========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "==========================================\n";

if ($failCount > 0) {
    exit(1);
}

