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

echo "=== Verifying Participant Flow for 'Memory Fragments' ===\n\n";

$contestant = User::where('role', 'contestant')->firstOrFail();
$challenge = Challenge::where('slug', 'memory-fragments')->firstOrFail();

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
echo " - Contains 'Memory Fragments': " . (strpos($content, 'Memory Fragments') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Download Evidence': " . (strpos($content, 'Download Evidence') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'memory_dump.bin': " . (strpos($content, 'memory_dump.bin') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Memory Dump Evidence': " . (strpos($content, 'Memory Dump Evidence') !== false ? 'YES' : 'NO') . "\n";
echo " - Flag leaked in HTML: " . (strpos($content, 'fr4gm3nts') !== false ? 'YES (LEAK)' : 'NO (CLEAN)') . "\n\n";

// 2. Test Download Response
$dlResp = $controller->download($challenge);
$filePath = $dlResp->getFile()->getPathname();
echo "2. Download response:\n";
echo " - Status code: " . $dlResp->getStatusCode() . "\n";
echo " - Content disposition: " . $dlResp->headers->get('content-disposition') . "\n";
echo " - File size: " . filesize($filePath) . " bytes\n\n";

// 3. Extract Flag from Downloaded Binary Dump File
$raw = file_get_contents($filePath);
preg_match_all('/\[MEMFRAG:PART_(\d+):INDEX_(\d+)\/(\d+):([a-zA-Z0-9+\/=]+)\]/', $raw, $matches, PREG_SET_ORDER);
usort($matches, fn($a, $b) => (int)$a[2] <=> (int)$b[2]);

$recovered = '';
foreach ($matches as $m) {
    $recovered .= base64_decode($m[4]);
}

echo "3. Flag Recovery from Downloaded memory_dump.bin:\n";
echo " - Recovered flag: '$recovered'\n";
echo " - Flag matches target: " . ($recovered === 'p4dCTF{fr4gm3nts_0f_m3m0ry}' ? 'YES' : 'NO') . "\n\n";

// 4. Test Flag Submission & Scoring
echo "4. Flag Submission & Scoring:\n";
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();

$submitService = app(FlagSubmissionService::class);
$result = $submitService->attempt($contestant, $challenge, $recovered);

echo " - Submit status: {$result['status']}\n";
echo " - Points awarded: {$result['points_awarded']}\n";
echo " - Solve recorded in DB: " . (VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->exists() ? 'YES' : 'NO') . "\n";

// 5. Test Subsequent Duplicate Solve Submission
$dupResult = $submitService->attempt($contestant, $challenge, $recovered);
echo "5. Duplicate Solve Attempt:\n";
echo " - Subsequent status: {$dupResult['status']}\n";
echo " - Additional points: {$dupResult['points_awarded']}\n";

// Clean up test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();

echo "\nVerification complete!\n";

