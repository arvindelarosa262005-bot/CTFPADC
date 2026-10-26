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

echo "=== Verifying Participant Flow for 'The Silent Conversation' ===\n\n";

$contestant = User::where('role', 'contestant')->first();
$challenge = Challenge::where('slug', 'the-silent-conversation')->firstOrFail();

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
echo " - Contains 'The Silent Conversation': " . (strpos($content, 'The Silent Conversation') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Download PCAP': " . (strpos($content, 'Download PCAP') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'the_silent_conversation.pcap': " . (strpos($content, 'the_silent_conversation.pcap') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Network Traffic Capture': " . (strpos($content, 'Network Traffic Capture') !== false ? 'YES' : 'NO') . "\n";
echo " - Flag leaked in HTML: " . (strpos($content, 'tcp_str34m') !== false ? 'YES (LEAK)' : 'NO (CLEAN)') . "\n\n";

// 2. Test Download Response
$dlResp = $controller->download($challenge);
$filePath = $dlResp->getFile()->getPathname();
echo "2. Download response:\n";
echo " - Status code: " . $dlResp->getStatusCode() . "\n";
echo " - Content disposition: " . $dlResp->headers->get('content-disposition') . "\n";
echo " - Content type: " . $dlResp->headers->get('content-type') . "\n";
echo " - File size: " . filesize($filePath) . " bytes\n\n";

// 3. Extract Flag from Downloaded File
$raw = file_get_contents($filePath);
preg_match('/p4dCTF\{[a-zA-Z0-9_]+\}/', $raw, $matches);
$recovered = $matches[0] ?? '';
echo "3. Flag Recovery from Downloaded PCAP File:\n";
echo " - Recovered flag: '$recovered'\n";
echo " - Flag matches target: " . ($recovered === 'p4dCTF{tcp_str34m_r3c0v3r3d}' ? 'YES' : 'NO') . "\n\n";

// 4. Test Flag Submission & Scoring
echo "4. Flag Submission & Scoring:\n";
// Clear previous test solve if any
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

