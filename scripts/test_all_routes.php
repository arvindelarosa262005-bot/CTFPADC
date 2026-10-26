<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Challenge;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

echo "=== ROUTE INTEGRITY AUDIT ===\n";

$admin = User::where('role', 'admin')->first();
$contestant = User::where('role', 'contestant')->first();

$routeCollection = Route::getRoutes();
$totalRoutes = count($routeCollection);
echo "Total routes registered: {$totalRoutes}\n\n";

$tested = 0;
$passed = 0;
$failed = 0;

// Test all GET routes with both guest and authenticated users
foreach ($routeCollection as $route) {
    $methods = $route->methods();
    if (!in_array('GET', $methods)) {
        continue;
    }
    
    $uri = $route->uri();
    
    // Skip storage upload or wildcard routes that require parameters we need to substitute
    if (str_contains($uri, '{path}')) {
        continue;
    }
    
    // Substitute route parameters
    $testUri = $uri;
    if (str_contains($testUri, '{challenge:slug}')) {
        $ch = Challenge::first();
        $testUri = str_replace('{challenge:slug}', $ch->slug, $testUri);
    } elseif (str_contains($testUri, '{challenge}')) {
        $ch = Challenge::first();
        $testUri = str_replace('{challenge}', $ch->slug, $testUri);
    }
    
    if (str_contains($testUri, '{team}')) {
        $team = Team::first() ?? Team::create([
            'name' => 'RouteAuditTeam',
            'captain_id' => $contestant->id,
            'invitation_code_hash' => hash('sha256', '123456'),
            'invitation_code_encrypted' => '123456',
            'status' => 'active',
        ]);
        $testUri = str_replace('{team}', $team->id, $testUri);
    }
    
    if (str_contains($testUri, '{user}')) {
        $testUri = str_replace('{user}', $contestant->id, $testUri);
    }
    
    if (str_contains($testUri, '{membership}')) {
        continue;
    }
    
    $tested++;
    
    // Test as Admin
    Auth::login($admin);
    $req = Request::create('/' . ltrim($testUri, '/'), 'GET');
    $resp = $kernel->handle($req);
    $code = $resp->getStatusCode();
    
    if ($code >= 500) {
        $failed++;
        echo " [HTTP $code FAIL] Admin GET /{$testUri} -> 500 ERROR!\n";
    } else {
        $passed++;
        echo " [HTTP $code OK] Admin GET /{$testUri}\n";
    }
    
    // Test as Contestant
    Auth::login($contestant);
    $req2 = Request::create('/' . ltrim($testUri, '/'), 'GET');
    $resp2 = $kernel->handle($req2);
    $code2 = $resp2->getStatusCode();
    
    if ($code2 >= 500) {
        $failed++;
        echo " [HTTP $code2 FAIL] Contestant GET /{$testUri} -> 500 ERROR!\n";
    } else {
        $passed++;
        echo " [HTTP $code2 OK] Contestant GET /{$testUri}\n";
    }
}

// Clean up temporary team if created
Team::where('name', 'RouteAuditTeam')->delete();

echo "\nSummary: Tested {$tested} GET route patterns. Passed: {$passed}, Failed (HTTP 500): {$failed}\n";
if ($failed > 0) {
    exit(1);
}
exit(0);

