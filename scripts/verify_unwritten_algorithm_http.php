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

echo "=== Verifying Participant Flow for 'The Unwritten Algorithm' ===\n\n";

$contestant = User::where('role', 'contestant')->firstOrFail();
$challenge = Challenge::where('slug', 'the-unwritten-algorithm')->firstOrFail();

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
echo " - Contains 'The Unwritten Algorithm': " . (strpos($content, 'The Unwritten Algorithm') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Download Package': " . (strpos($content, 'Download Package') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'the_unwritten_algorithm.zip': " . (strpos($content, 'the_unwritten_algorithm.zip') !== false ? 'YES' : 'NO') . "\n";
echo " - Contains 'Open Verification Portal': " . (strpos($content, 'Open Verification Portal') !== false ? 'YES' : 'NO') . "\n";
echo " - Flag leaked in HTML: " . (strpos($content, 'h1dd3n_l0g1c') !== false ? 'YES (LEAK)' : 'NO (CLEAN)') . "\n\n";

// 2. Test Download Response
$dlResp = $controller->download($challenge);
$filePath = $dlResp->getFile()->getPathname();
echo "2. Download response:\n";
echo " - Status code: " . $dlResp->getStatusCode() . "\n";
echo " - Content disposition: " . $dlResp->headers->get('content-disposition') . "\n";
echo " - File size: " . filesize($filePath) . " bytes\n\n";

// 3. Online Verification Request
echo "3. Online Target Verification:\n";
$targetController = app(\App\Http\Controllers\ChallengeTargetController::class);
$verifyReq = Request::create(route('challenges.target.verify', ['challenge' => $challenge]), 'POST', [
    'candidate_output' => '}63676__zAcA7Ugk1_fm0w8c84O{wQuS1',
]);
$verifyResp = $targetController->verify($challenge, $verifyReq);
$verifyData = json_decode($verifyResp->getContent(), true);

echo " - Verify HTTP status: " . $verifyResp->getStatusCode() . "\n";
echo " - Success: " . ($verifyData['success'] ? 'YES' : 'NO') . "\n";
echo " - Unlocked Flag: " . ($verifyData['flag'] ?? 'NONE') . "\n\n";

$recoveredFlag = $verifyData['flag'] ?? '';

// 4. Test Flag Submission & Scoring
echo "4. Flag Submission & Scoring:\n";
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();

$submitService = app(FlagSubmissionService::class);
$result = $submitService->attempt($contestant, $challenge, $recoveredFlag);

echo " - Submit status: {$result['status']}\n";
echo " - Points awarded: {$result['points_awarded']}\n";
echo " - Solve recorded in DB: " . (VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->exists() ? 'YES' : 'NO') . "\n";

// 5. Test Subsequent Duplicate Solve Submission
$dupResult = $submitService->attempt($contestant, $challenge, $recoveredFlag);
echo "5. Duplicate Solve Attempt:\n";
echo " - Subsequent status: {$dupResult['status']}\n";
echo " - Additional points: {$dupResult['points_awarded']}\n";

// Clean up test solve
VerifiedSolve::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();
ChallengeSubmission::where('challenge_id', $challenge->id)->where('user_id', $contestant->id)->delete();

echo "\nVerification complete!\n";

