<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Challenge;
use App\Models\VerifiedSolve;
use App\Models\ChallengeSubmission;
use App\Services\FlagSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$initReq = Request::create('/');
$app->instance('request', $initReq);

echo "=== Verifying Participant Flow for Silent Evidence ===\n\n";

$contestant = User::where('role', 'contestant')->first();
$challenge = Challenge::where('slug', 'silent-evidence')->firstOrFail();

echo "Contestant: {$contestant->username} (ID: {$contestant->id})\n";
echo "Challenge: {$challenge->title} (ID: {$challenge->id})\n\n";

Auth::login($contestant);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());

// 1. Test Controller Show (Modal AJAX)
$controller = app(\App\Http\Controllers\ChallengeController::class);
$ajaxReq = Request::create(route('challenges.show', ['challenge' => $challenge]), 'GET');
$ajaxReq->headers->set('X-Requested-With', 'XMLHttpRequest');
$ajaxReq->setUserResolver(fn () => $contestant);
$view = $controller->show($challenge, $ajaxReq);
$content = $view->render();

echo "1. Controller modal render length: " . strlen($content) . " bytes\n";
echo " - Contains 'Silent Evidence': " . (strpos($content, 'Silent Evidence') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Download Image': " . (strpos($content, 'Download Image') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'silent_evidence.png': " . (strpos($content, 'silent_evidence.png') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Evidence Image': " . (strpos($content, 'Evidence Image') !== false ? 'YES' : 'NO') . "\n";
echo " - Flag leaked in HTML: " . (strpos($content, 'sh4d0ws') !== false ? 'YES (LEAK)' : 'NO (CLEAN)') . "\n\n";

// 2. Test Download Response
$dlResp = $controller->download($challenge);
$filePath = $dlResp->getFile()->getPathname();
echo "2. Download response:\n";
echo " - Status code: " . $dlResp->getStatusCode() . "\n";
echo " - Content disposition: " . $dlResp->headers->get('content-disposition') . "\n";
echo " - Content type: " . $dlResp->headers->get('content-type') . "\n";
echo " - File size: " . filesize($filePath) . " bytes\n\n";

// 3. Extract Flag from Downloaded File LSBs
$raw = file_get_contents($filePath);
$offset = 8;
$idat = '';
while ($offset < strlen($raw)) {
    $len = unpack('N', substr($raw, $offset, 4))[1];
    $type = substr($raw, $offset + 4, 4);
    $chunkData = substr($raw, $offset + 8, $len);
    $offset += 12 + $len;
    if ($type === 'IDAT') {
        $idat .= $chunkData;
    }
}
$uncompressed = gzuncompress($idat);
$width = 447;
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
for ($y = 0; $y < 2; $y++) {
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
$recovered = '';
for ($i = 0; $i < count($bits); $i += 8) {
    $byteVal = 0;
    for ($b = 0; $b < 8; $b++) {
        $byteVal = ($byteVal << 1) | $bits[$i + $b];
    }
    if ($byteVal === 0) break;
    $recovered .= chr($byteVal);
}
echo "3. LSB Flag Recovery from Downloaded File:\n";
echo " - Recovered flag: '$recovered'\n";
echo " - Flag matches target: " . ($recovered === 'p4dCTF{sh4d0ws_h1d3_s3cr3ts}' ? 'YES' : 'NO') . "\n\n";

// 4. Test Flag Submission & Scoring
echo "4. Flag Submission & Scoring:\n";
// Clear previous test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();

$submitService = app(FlagSubmissionService::class);
$result = $submitService->attempt($contestant, $challenge, $recovered);

echo " - Submit status: {$result['status']}\n";
echo " - Points awarded: {$result['points_awarded']}\n";
echo " - Solve recorded in DB: " . (VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->exists() ? 'YES' : 'NO') . "\n";

// Clean up test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();

echo "\nVerification complete!\n";
