<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Http\Request;

$initReq = Request::create('/');
$app->instance('request', $initReq);

echo "=== Verifying HTTP Participant Flow ===\n";

$contestant = User::where('role', 'contestant')->first();
$challenge = Challenge::where('slug', 'hidden-in-the-hounds')->first();

\Illuminate\Support\Facades\Auth::login($contestant);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());

// Test Controller Directly
$controller = app(\App\Http\Controllers\ChallengeController::class);
$ajaxReq = Request::create(route('challenges.show', ['challenge' => $challenge]), 'GET');
$ajaxReq->headers->set('X-Requested-With', 'XMLHttpRequest');
$ajaxReq->setUserResolver(fn () => $contestant);
$view = $controller->show($challenge, $ajaxReq);
$content = $view->render();

echo "Direct controller modal render length: " . strlen($content) . " bytes\n";
echo "Contains 'Hidden in the Hounds': " . (strpos($content, 'Hidden in the Hounds') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Download Image': " . (strpos($content, 'Download Image') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'popular_dog_breeds.png': " . (strpos($content, 'popular_dog_breeds.png') !== false ? 'YES' : 'NO') . "\n";
echo "Contains 'Evidence Image': " . (strpos($content, 'Evidence Image') !== false ? 'YES' : 'NO') . "\n";

// Test Download directly
$dlResp = $controller->download($challenge);
echo "Download status code: " . $dlResp->getStatusCode() . "\n";
echo "Download content disposition: " . $dlResp->headers->get('content-disposition') . "\n";
echo "Download content type: " . $dlResp->headers->get('content-type') . "\n";
echo "Download file size: " . filesize($dlResp->getFile()->getPathname()) . " bytes\n";
