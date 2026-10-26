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

echo "=== P4DC CTF: Test 'Silent Evidence' Steganography Challenge ===\n\n";

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
$challenge = Challenge::where('slug', 'silent-evidence')->first();
assertTest($challenge !== null, "Challenge 'silent-evidence' exists in database");
assertTest($challenge?->isLive() === true, "Challenge is active and published (Live)");
assertTest($challenge?->title === 'Silent Evidence', "Challenge title is 'Silent Evidence'");
assertTest($challenge?->category === 'steganography', "Category is 'steganography'");
assertTest($challenge?->difficulty === 'medium', "Difficulty is 'medium'");
assertTest($challenge?->points === 50, "Points is 50");
assertTest($challenge?->hasAttachment() === true, "Challenge hasAttachment is true");
assertTest($challenge?->attachment_filename === 'silent_evidence.png', "Attachment filename is 'silent_evidence.png'");

// 2. Verify Attachment File on Storage
$storagePath = Storage::disk('public')->path($challenge->attachment_path);
assertTest(file_exists($storagePath), "Attachment file exists on disk: $storagePath");
$fileSize = filesize($storagePath);
assertTest($fileSize > 50000, "Attachment file is substantial image (Size: $fileSize bytes)");

// 3. Inspect Raw Binary for Flag Leaks (Strings & Metadata)
$rawContents = file_get_contents($storagePath);
assertTest(strpos($rawContents, 'p4dCTF') === false, "Raw file contains NO plaintext flag in strings/metadata");
assertTest(strpos($rawContents, 'sh4d0ws') === false, "Raw file contains NO flag fragments in strings/metadata");

// 4. Verify Image File Properties & Format
$imgInfo = getimagesize($storagePath);
assertTest($imgInfo !== false && $imgInfo[0] === 447 && $imgInfo[1] === 447, "Image is valid 447x447 image");
assertTest($imgInfo['mime'] === 'image/png', "Image MIME type is image/png");

// 5. Test Modal & Detail Rendering (No flag leakage in HTML, working preview & download links)
$testUser = User::where('role', 'contestant')->first();
if (!$testUser) {
    $testUser = User::create([
        'username' => 'silent_tester_' . time(),
        'email' => 'silent_tester_' . time() . '@example.com',
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

assertTest(strpos($modalHtml, 'silent_evidence.png') !== false, "Modal contains reference to 'silent_evidence.png'");
assertTest(strpos($modalHtml, 'Download Image') !== false, "Modal contains 'Download Image' button");
assertTest(strpos($modalHtml, 'Evidence Image') !== false, "Modal contains Evidence Image preview");
assertTest(strpos($modalHtml, 'sh4d0ws_h1d3_s3cr3ts') === false, "Modal HTML does NOT leak canonical flag");
assertTest(strpos($modalHtml, 'placeholder="p4dCTF{...}"') !== false, "Modal contains updated placeholder 'p4dCTF{...}'");
assertTest(strpos($modalHtml, 'sh4d0ws') === false, "Modal HTML does NOT leak flag fragments");

// Check hints for spoilers
foreach ($challenge->hintList() as $idx => $hint) {
    assertTest(strpos($hint, 'p4dCTF') === false && strpos($hint, 'sh4d0ws') === false, "Hint " . ($idx + 1) . " does not spoil flag");
}

// 6. Test Download Controller Endpoint
$controller = app(\App\Http\Controllers\ChallengeController::class);
$downloadResp = $controller->download($challenge);
assertTest($downloadResp->getStatusCode() === 200, "Download controller returns HTTP 200");
$headers = $downloadResp->headers;
$contentDisposition = $headers->get('content-disposition');
assertTest(strpos($contentDisposition, 'silent_evidence.png') !== false, "Content-Disposition specifies 'silent_evidence.png'");

// 7. Independent Steganography Extraction (Simulating Player Analysis)
echo "\n--- Independent LSB Extraction ---\n";
$offset = 8;
$idat = '';
while ($offset < strlen($rawContents)) {
    $len = unpack('N', substr($rawContents, $offset, 4))[1];
    $type = substr($rawContents, $offset + 4, 4);
    $chunkData = substr($rawContents, $offset + 8, $len);
    $offset += 12 + $len;
    if ($type === 'IDAT') {
        $idat .= $chunkData;
    }
}

$uncompressed = gzuncompress($idat);
$width = 447;
$height = 447;
$bpp = 3;
$stride = 1 + $width * $bpp;

function paethTest($a, $b, $c) {
    $p = $a + $b - $c;
    $pa = abs($p - $a);
    $pb = abs($p - $b);
    $pc = abs($p - $c);
    if ($pa <= $pb && $pa <= $pc) return $a;
    if ($pb <= $pc) return $b;
    return $c;
}

$prevRow = array_fill(0, $width * $bpp, 0);
$bits = [];

for ($y = 0; $y < min($height, 5); $y++) {
    $filter = ord($uncompressed[$y * $stride]);
    $currRow = [];
    for ($i = 0; $i < $width * $bpp; $i++) {
        $x = ord($uncompressed[$y * $stride + 1 + $i]);
        $a = ($i >= $bpp) ? $currRow[$i - $bpp] : 0;
        $b = $prevRow[$i];
        $c = ($i >= $bpp) ? $prevRow[$i - $bpp] : 0;
        switch ($filter) {
            case 0: $val = $x; break;
            case 1: $val = ($x + $a) & 0xFF; break;
            case 2: $val = ($x + $b) & 0xFF; break;
            case 3: $val = ($x + floor(($a + $b) / 2)) & 0xFF; break;
            case 4: $val = ($x + paethTest($a, $b, $c)) & 0xFF; break;
            default: $val = $x; break;
        }
        $currRow[$i] = $val;
        if (count($bits) < 512) {
            $bits[] = $val & 1;
        }
    }
    $prevRow = $currRow;
}

$extracted = '';
for ($i = 0; $i < count($bits); $i += 8) {
    $byteVal = 0;
    for ($b = 0; $b < 8; $b++) {
        $byteVal = ($byteVal << 1) | $bits[$i + $b];
    }
    if ($byteVal === 0) break;
    $extracted .= chr($byteVal);
}

echo "Extracted flag from PNG LSB: '$extracted'\n";
assertTest($extracted === 'p4dCTF{sh4d0ws_h1d3_s3cr3ts}', "Extracted flag exactly matches 'p4dCTF{sh4d0ws_h1d3_s3cr3ts}'");

// 8. Server-Side Flag Validation & Scoring Integration
echo "\n--- Server-Side Flag Validation & Scoring ---\n";
// Clear any previous test solves
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

$service = app(FlagSubmissionService::class);

// Wrong flag test
$wrongRes = $service->attempt($testUser, $challenge, 'p4dCTF{wrong_flag_guess}');
assertTest($wrongRes['status'] === 'incorrect', "Incorrect flag submission returns 'incorrect'");

// Correct flag test
$correctRes = $service->attempt($testUser, $challenge, $extracted);
assertTest($correctRes['status'] === 'solved', "Correct extracted flag submission returns 'solved'");
assertTest($correctRes['points_awarded'] === 50, "Awarded points equals 50");

// Verify DB solve record
$solveRecord = VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->first();
assertTest($solveRecord !== null && $solveRecord->points_awarded === 50, "VerifiedSolve record created with 50 points");

// Re-submission test
$dupRes = $service->attempt($testUser, $challenge, $extracted);
assertTest($dupRes['status'] === 'already_solved', "Subsequent submission returns 'already_solved'");

// Clean up test solve so contestant account is clean
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

// 9. Primary Admin Account & Other Challenges Preservation
echo "\n--- System Integrity Preservation ---\n";
$admin = User::where('username', 'admin')->first();
assertTest($admin !== null && $admin->role === 'admin' && $admin->is_active, "Primary admin account remains intact and active");

$totalChallenges = Challenge::count();
assertTest($totalChallenges >= 8, "All challenges preserved (Total: $totalChallenges)");

echo "\n==========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "==========================================\n";

if ($failCount > 0) {
    exit(1);
}

