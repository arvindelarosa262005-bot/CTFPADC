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

echo "=== P4DC CTF: Test 'The Silent Conversation' Networking Challenge ===\n\n";

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
$challenge = Challenge::where('slug', 'the-silent-conversation')->first();
assertTest($challenge !== null, "Challenge 'the-silent-conversation' exists in database");
assertTest($challenge?->isLive() === true, "Challenge is active and published (Live)");
assertTest($challenge?->title === 'The Silent Conversation', "Challenge title is 'The Silent Conversation'");
assertTest($challenge?->category === 'networking', "Category is 'networking'");
assertTest($challenge?->difficulty === 'easy', "Difficulty is 'easy'");
assertTest($challenge?->points === 60, "Points is 60");
assertTest($challenge?->hasAttachment() === true, "Challenge hasAttachment is true");
assertTest($challenge?->attachment_filename === 'the_silent_conversation.pcap', "Attachment filename is 'the_silent_conversation.pcap'");

// 2. Verify Attachment File on Storage
$storagePath = Storage::disk('public')->path($challenge->attachment_path);
assertTest(file_exists($storagePath), "Attachment file exists on disk: $storagePath");
$fileSize = filesize($storagePath);
assertTest($fileSize > 2000, "Attachment file is valid PCAP capture (Size: $fileSize bytes)");

// 3. Inspect PCAP Header (Magic 0xa1b2c3d4 or 0xd4c3b2a1)
$rawContents = file_get_contents($storagePath);
$magic = unpack('V', substr($rawContents, 0, 4))[1];
assertTest($magic === 0xa1b2c3d4 || $magic === 0xd4c3b2a1, "PCAP file has valid libpcap global magic number (0x" . dechex($magic) . ")");
$versionMajor = unpack('v', substr($rawContents, 4, 2))[1];
$versionMinor = unpack('v', substr($rawContents, 6, 2))[1];
assertTest($versionMajor === 2 && $versionMinor === 4, "PCAP version is 2.4");
$network = unpack('V', substr($rawContents, 20, 4))[1];
assertTest($network === 1, "PCAP link-layer type is Ethernet (1)");

// 4. Inspect Description & Hints for Leaks (No flag or keyword leakage)
assertTest(strpos($challenge->description, 'p4dCTF') === false, "Description does NOT leak flag");
assertTest(strpos($challenge->description, 'tcp_str34m') === false, "Description does NOT leak keyword");
assertTest(strpos($challenge->description, '9001') === false, "Description does NOT spoonfeed port number");

foreach ($challenge->hintList() as $idx => $hint) {
    assertTest(strpos($hint, 'p4dCTF') === false && strpos($hint, 'tcp_str34m') === false, "Hint " . ($idx + 1) . " does not spoil flag");
    assertTest(strpos($hint, '9001') === false && strpos($hint, 'tcp.stream eq') === false, "Hint " . ($idx + 1) . " avoids spoon-feeding exact filters");
}

// 5. Test Modal & Detail Rendering (No flag leakage in HTML, working Download PCAP button)
$testUser = User::where('role', 'contestant')->first();
if (!$testUser) {
    $testUser = User::create([
        'username' => 'net_tester_' . time(),
        'email' => 'net_tester_' . time() . '@example.com',
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

assertTest(strpos($modalHtml, 'the_silent_conversation.pcap') !== false, "Modal contains reference to 'the_silent_conversation.pcap'");
assertTest(strpos($modalHtml, 'Download PCAP') !== false, "Modal contains 'Download PCAP' button");
assertTest(strpos($modalHtml, 'Network Traffic Capture') !== false, "Modal contains Network Traffic Capture section");
assertTest(strpos($modalHtml, 'tcp_str34m_r3c0v3r3d') === false, "Modal HTML does NOT leak canonical flag");
assertTest(strpos($modalHtml, 'placeholder="p4dCTF{...}"') !== false, "Modal contains placeholder 'p4dCTF{...}'");

// Detail view rendering
$detailHtml = view('challenges.detail', [
    'challenge' => $challenge,
    'isSolved' => false,
    'targetUrl' => $challenge->resolveTargetUrl(),
    'hints' => $challenge->hintList(),
])->render();
assertTest(strpos($detailHtml, 'Download PCAP') !== false, "Detail page contains 'Download PCAP' button");
assertTest(strpos($detailHtml, 'tcp_str34m_r3c0v3r3d') === false, "Detail HTML does NOT leak flag");

// 6. Test Download Controller Endpoint
$controller = app(\App\Http\Controllers\ChallengeController::class);
$downloadResp = $controller->download($challenge);
assertTest($downloadResp->getStatusCode() === 200, "Download controller returns HTTP 200");
$headers = $downloadResp->headers;
$contentDisposition = $headers->get('content-disposition');
assertTest(strpos($contentDisposition, 'the_silent_conversation.pcap') !== false, "Content-Disposition specifies 'the_silent_conversation.pcap'");

// 7. Independent Packet Analysis (Extracting TCP conversation from PCAP bytes)
echo "\n--- Independent Traffic Analysis & Flag Extraction ---\n";
$offset = 24; // skip global header
$packetCount = 0;
$tcpStreams = [];

while ($offset < strlen($rawContents)) {
    if ($offset + 16 > strlen($rawContents)) break;
    $hdr = unpack('Vts_sec/Vts_usec/Vincl_len/Vorig_len', substr($rawContents, $offset, 16));
    $offset += 16;
    $inclLen = $hdr['incl_len'];
    if ($offset + $inclLen > strlen($rawContents)) break;
    $pktData = substr($rawContents, $offset, $inclLen);
    $offset += $inclLen;
    $packetCount++;

    // Ethernet
    if ($inclLen < 14) continue;
    $ethertype = unpack('n', substr($pktData, 12, 2))[1];
    if ($ethertype !== 0x0800) continue; // Only IPv4

    // IPv4
    $ipData = substr($pktData, 14);
    if (strlen($ipData) < 20) continue;
    $ihl = (ord($ipData[0]) & 0x0f) * 4;
    $proto = ord($ipData[9]);
    $srcIp = inet_ntop(substr($ipData, 12, 4));
    $dstIp = inet_ntop(substr($ipData, 16, 4));

    if ($proto === 6) { // TCP
        $tcpData = substr($ipData, $ihl);
        if (strlen($tcpData) < 20) continue;
        $srcPort = unpack('n', substr($tcpData, 0, 2))[1];
        $dstPort = unpack('n', substr($tcpData, 2, 2))[1];
        $dataOffset = ((ord($tcpData[12]) >> 4) & 0x0f) * 4;
        $payload = substr($tcpData, $dataOffset);

        // Group into bidirectional stream key
        $ep1 = "$srcIp:$srcPort";
        $ep2 = "$dstIp:$dstPort";
        $streamKey = $ep1 < $ep2 ? "$ep1 <-> $ep2" : "$ep2 <-> $ep1";
        if (!isset($tcpStreams[$streamKey])) {
            $tcpStreams[$streamKey] = '';
        }
        $tcpStreams[$streamKey] .= $payload;
    }
}

echo "Total packets in capture: $packetCount\n";
echo "Total distinct TCP conversations: " . count($tcpStreams) . "\n";
assertTest($packetCount >= 30, "Capture contains realistic volume of traffic (count: $packetCount)");
assertTest(count($tcpStreams) >= 3, "Capture contains multiple TCP conversations (count: " . count($tcpStreams) . ")");

// Search streams for flag pattern
$extractedFlag = null;
foreach ($tcpStreams as $convo => $payload) {
    if (preg_match('/p4dCTF\{[a-zA-Z0-9_]+\}/', $payload, $match)) {
        echo "Found flag in conversation: $convo\n";
        $extractedFlag = $match[0];
        break;
    }
}

assertTest($extractedFlag !== null, "Extracted flag from TCP stream conversation");
echo "Recovered Flag: '$extractedFlag'\n";
assertTest($extractedFlag === 'p4dCTF{tcp_str34m_r3c0v3r3d}', "Recovered flag matches 'p4dCTF{tcp_str34m_r3c0v3r3d}' exactly");

// 8. Server-Side Flag Validation & Scoring Integration
echo "\n--- Server-Side Flag Validation & Scoring ---\n";
// Clear any existing solves for this test
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

$service = app(FlagSubmissionService::class);

// Wrong flag test
$wrongRes = $service->attempt($testUser, $challenge, 'p4dCTF{wrong_traffic_guess}');
assertTest($wrongRes['status'] === 'incorrect', "Incorrect flag submission returns 'incorrect'");

// Correct flag test
$correctRes = $service->attempt($testUser, $challenge, $extractedFlag);
assertTest($correctRes['status'] === 'solved', "Correct flag submission returns 'solved'");
assertTest($correctRes['points_awarded'] === 60, "Awarded points equals 60 (configured challenge points)");

// Verify DB solve record
$solveRecord = VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->first();
assertTest($solveRecord !== null && $solveRecord->points_awarded === 60, "VerifiedSolve record created with 60 points");

// Verify Leaderboard / User Total Points
$userPoints = (int) VerifiedSolve::where('user_id', $testUser->id)->sum('points_awarded');
assertTest($userPoints >= 60, "User leaderboard/verified points reflects the 60 points awarded (current sum: $userPoints)");

// Re-submission test
$dupRes = $service->attempt($testUser, $challenge, $extractedFlag);
assertTest($dupRes['status'] === 'already_solved', "Subsequent submission returns 'already_solved'");

// Clean up test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $testUser->id)->delete();

// 9. Primary Admin Account & Other Challenges Preservation
echo "\n--- System Integrity Preservation ---\n";
$admin = User::where('username', 'admin')->first();
assertTest($admin !== null && $admin->role === 'admin' && $admin->is_active, "Primary admin account remains intact and active (ID: " . ($admin?->id ?? 'none') . ")");

$totalChallenges = Challenge::count();
assertTest($totalChallenges >= 9, "All challenges preserved (Total: $totalChallenges)");

echo "\n==========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "==========================================\n";

if ($failCount > 0) {
    exit(1);
}

