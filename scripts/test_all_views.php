<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;

$admin = User::where('role', 'admin')->first();
$contestant = User::where('role', 'contestant')->first();

Auth::login($contestant);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());

echo "=== TESTING CHALLENGES VIEWS ===\n";

// 1. Challenges index without openChallenge
try {
    $challenges = Challenge::where('is_active', true)->get();
    $htmlIndex = view('challenges.index', [
        'challenges' => $challenges,
        'solvedIds' => [4, 21],
    ])->render();
    echo " [PASS] view('challenges.index') rendered (" . strlen($htmlIndex) . " bytes)\n";
} catch (\Throwable $e) {
    echo " [FAIL] view('challenges.index') - " . $e->getMessage() . "\n";
}

// 2. Challenges index WITH openChallenge (modal inclusion)
foreach ($challenges as $ch) {
    try {
        $modalData = [
            'challenge' => $ch,
            'isSolved' => false,
            'targetUrl' => $ch->resolveTargetUrl(),
            'hints' => $ch->hintList(),
        ];
        $htmlWithModal = view('challenges.index', [
            'challenges' => $challenges,
            'solvedIds' => [4, 21],
            'openChallenge' => $ch,
            'modalData' => $modalData,
        ])->render();
        echo " [PASS] view('challenges.index') with openChallenge={$ch->slug} (" . strlen($htmlWithModal) . " bytes)\n";
    } catch (\Throwable $e) {
        echo " [FAIL] view('challenges.index') with openChallenge={$ch->slug} - " . $e->getMessage() . "\n";
    }
}

// 3. Direct _modal rendering (as AJAX request)
foreach ($challenges as $ch) {
    try {
        $modalHtml = view('challenges._modal', [
            'challenge' => $ch,
            'isSolved' => false,
            'targetUrl' => $ch->resolveTargetUrl(),
            'hints' => $ch->hintList(),
        ])->render();
        echo " [PASS] view('challenges._modal') for {$ch->slug} (" . strlen($modalHtml) . " bytes)\n";
    } catch (\Throwable $e) {
        echo " [FAIL] view('challenges._modal') for {$ch->slug} - " . $e->getMessage() . "\n";
    }
}

// 4. Admin dashboard rendering
Auth::login($admin);
try {
    $ctrl = app(App\Http\Controllers\Admin\AdminDashboardController::class);
    $adminView = $ctrl->index();
    $adminHtml = $adminView->render();
    echo " [PASS] AdminDashboardController::index() rendered (" . strlen($adminHtml) . " bytes)\n";
} catch (\Throwable $e) {
    echo " [FAIL] AdminDashboardController::index() - " . $e->getMessage() . "\n";
}

