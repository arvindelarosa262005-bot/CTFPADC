<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

echo "=== P4DC CTF: Test 6-Digit Team Invitation System ===\n\n";

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

// Clean up previous test artifacts
Team::where('name', 'like', 'Hex6_Test_%')->update(['captain_id' => null]);
TeamMembership::whereHas('user', fn($q) => $q->where('username', 'like', 'hex6_user_%'))->delete();
Team::where('name', 'like', 'Hex6_Test_%')->delete();
User::where('username', 'like', 'hex6_user_%')->delete();

$initReq = Request::create('/');
$app->instance('request', $initReq);

// Setup Test Users
$captain = User::create([
    'username' => 'hex6_user_capt',
    'email' => 'hex6_user_capt@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$joiner1 = User::create([
    'username' => 'hex6_user_join1',
    'email' => 'hex6_user_join1@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$joiner2 = User::create([
    'username' => 'hex6_user_join2',
    'email' => 'hex6_user_join2@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$teamController = app(\App\Http\Controllers\Contestant\TeamController::class);

// 1. Test Team Creation & 6-Digit Code Generation
echo "\n--- 1. Testing Team Creation & 6-Digit Code Generation ---\n";
Auth::login($captain);
$storeReq = Request::create(route('teams.store'), 'POST', ['name' => 'Hex6_Test_Alpha']);
$storeReq->setUserResolver(fn () => $captain);

$storeResp = $teamController->store($storeReq);
assertTest($storeResp->getStatusCode() === 302, "Team created successfully and redirected");

$team = Team::where('name', 'Hex6_Test_Alpha')->first();
assertTest($team !== null, "Team 'Hex6_Test_Alpha' exists in database");

$code = (string) $team->invitation_code_encrypted;
echo "Generated invitation code: '$code'\n";
assertTest(strlen($code) === 6, "Invitation code is exactly 6 characters long (got " . strlen($code) . ")");
assertTest(ctype_digit($code), "Invitation code contains only numeric digits");
assertTest(preg_match('/^[0-9]{6}$/', $code) === 1, "Invitation code matches ^[0-9]{6}$ pattern");
assertTest(hash('sha256', $code) === $team->invitation_code_hash, "SHA-256 hash matches invitation_code_hash column");

// 2. Test Code Uniqueness Across Multiple Teams
echo "\n--- 2. Testing Code Uniqueness Across Multiple Teams ---\n";
$generatedCodes = [$code];
for ($i = 1; $i <= 5; $i++) {
    $tempCaptain = User::create([
        'username' => "hex6_user_c$i",
        'email' => "hex6_user_c$i@example.com",
        'password' => bcrypt('password123'),
        'role' => 'contestant',
        'is_active' => true,
    ]);
    Auth::login($tempCaptain);
    $req = Request::create(route('teams.store'), 'POST', ['name' => "Hex6_Test_Team_$i"]);
    $req->setUserResolver(fn () => $tempCaptain);
    $teamController->store($req);

    $t = Team::where('name', "Hex6_Test_Team_$i")->first();
    $c = (string) $t->invitation_code_encrypted;
    assertTest(preg_match('/^[0-9]{6}$/', $c) === 1, "Team $i generated valid 6-digit code: $c");
    assertTest(!in_array($c, $generatedCodes), "Team $i code is unique and has no collision");
    $generatedCodes[] = $c;
}

// 3. Test Valid Join With 6-Digit Code
echo "\n--- 3. Testing Valid Join With 6-Digit Code ---\n";
Auth::login($joiner1);
$joinReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $code]);
$joinReq->setUserResolver(fn () => $joiner1);

$joinResp = $teamController->join($joinReq);
assertTest($joinResp->getStatusCode() === 302, "Join with valid 6-digit code redirects to team profile");

$membership = TeamMembership::where('team_id', $team->id)->where('user_id', $joiner1->id)->first();
assertTest($membership !== null, "User successfully joined team (membership record created)");
assertTest($membership?->role === 'member', "User role is 'member'");
assertTest($membership?->left_at === null, "Membership is active (left_at is null)");

// 4. Test Invalid 6-Digit Codes
echo "\n--- 4. Testing Invalid 6-Digit Codes ---\n";
Auth::login($joiner2);
$invalidCode = '000000';
while (in_array($invalidCode, $generatedCodes)) {
    $invalidCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

$invalidReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $invalidCode]);
$invalidReq->setUserResolver(fn () => $joiner2);

$caughtInvalid = false;
$errorMessage = '';
try {
    $teamController->join($invalidReq);
} catch (ValidationException $e) {
    $caughtInvalid = true;
    $errors = $e->errors();
    $errorMessage = $errors['invitation_code'][0] ?? '';
}

assertTest($caughtInvalid, "ValidationException thrown for non-existent 6-digit code");
assertTest($errorMessage === 'That invitation code is invalid or no longer active.', "Error message is generic: '$errorMessage'");
assertTest(strpos($errorMessage, 'Hex6_Test_Alpha') === false, "Error message reveals NO team details");
assertTest(TeamMembership::where('user_id', $joiner2->id)->doesntExist(), "User was not added to any team");

// 5. Test Rejection of Codes Shorter Than 6 Digits
echo "\n--- 5. Testing Rejection of Short / Long / Non-Numeric Codes ---\n";
$shortCodes = ['1', '12', '123', '1234', '12345'];
foreach ($shortCodes as $sc) {
    $shortReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $sc]);
    $shortReq->setUserResolver(fn () => $joiner2);
    $caughtShort = false;
    try {
        $teamController->join($shortReq);
    } catch (ValidationException $e) {
        $caughtShort = true;
    }
    assertTest($caughtShort, "Code shorter than 6 digits ('$sc') is rejected by validation");
}

// Test Rejection of Codes Longer Than 6 Digits
$longCodes = ['1234567', '12345678', '8f16a715b1bdddcf78a747fe1665e9d1e0153a0329c1242c'];
foreach ($longCodes as $lc) {
    $longReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $lc]);
    $longReq->setUserResolver(fn () => $joiner2);
    $caughtLong = false;
    try {
        $teamController->join($longReq);
    } catch (ValidationException $e) {
        $caughtLong = true;
    }
    assertTest($caughtLong, "Code longer than 6 digits ('" . substr($lc, 0, 10) . "...') is rejected by validation");
}

// Test Rejection of Non-Numeric Codes (6 characters but alphanumeric)
$alphaCodes = ['abcdef', '12345a', '48291f', '!@#$%^', ' 48291'];
foreach ($alphaCodes as $ac) {
    $alphaReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $ac]);
    $alphaReq->setUserResolver(fn () => $joiner2);
    $caughtAlpha = false;
    try {
        $teamController->join($alphaReq);
    } catch (ValidationException $e) {
        $caughtAlpha = true;
    }
    assertTest($caughtAlpha, "Non-numeric 6-char code ('$ac') is rejected by validation");
}

// 6. Test Captain Regeneration
echo "\n--- 6. Testing Captain Invitation Code Regeneration ---\n";
Auth::login($captain);
$regenReq = Request::create(route('teams.invitation.regenerate', $team), 'POST');
$regenReq->setUserResolver(fn () => $captain);

$regenResp = $teamController->regenerateInvitation($regenReq, $team);
assertTest($regenResp->getStatusCode() === 302, "Regeneration succeeds and redirects");

$team->refresh();
$newCode = (string) $team->invitation_code_encrypted;
echo "Regenerated code: '$newCode'\n";
assertTest(strlen($newCode) === 6 && ctype_digit($newCode), "Regenerated code is exactly 6 numeric digits");
assertTest($newCode !== $code, "Regenerated code is different from old code");

// Verify Old Code Fails
Auth::login($joiner2);
$oldJoinReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $code]);
$oldJoinReq->setUserResolver(fn () => $joiner2);
$caughtOld = false;
try {
    $teamController->join($oldJoinReq);
} catch (ValidationException $e) {
    $caughtOld = true;
}
assertTest($caughtOld, "Old revoked code is rejected after regeneration");

// Verify New Code Works
$newJoinReq = Request::create(route('teams.join.store'), 'POST', ['invitation_code' => $newCode]);
$newJoinReq->setUserResolver(fn () => $joiner2);
$newJoinResp = $teamController->join($newJoinReq);
assertTest($newJoinResp->getStatusCode() === 302, "New regenerated code allows joiner2 to join");
assertTest(TeamMembership::where('team_id', $team->id)->where('user_id', $joiner2->id)->exists(), "Joiner2 successfully joined using regenerated code");

// 7. Test Non-Captain Cannot Regenerate Code
echo "\n--- 7. Testing Permission Controls ---\n";
$unauthRegenReq = Request::create(route('teams.invitation.regenerate', $team), 'POST');
$unauthRegenReq->setUserResolver(fn () => $joiner1);
$blocked = false;
try {
    $teamController->regenerateInvitation($unauthRegenReq, $team);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() === 403) {
        $blocked = true;
    }
}
assertTest($blocked, "Non-captain member is forbidden (HTTP 403) from regenerating invitation code");

// 8. Test Admin Team Creation Generates 6-Digit Code
echo "\n--- 8. Testing Admin Team Creation ---\n";
$admin = User::where('username', 'admin')->first();
$contestantForAdmin = User::create([
    'username' => 'hex6_user_admin_c',
    'email' => 'hex6_user_admin_c@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

Auth::login($admin);
$adminTeamController = app(\App\Http\Controllers\Admin\AdminTeamController::class);
$adminReq = Request::create(route('admin.teams.store'), 'POST', [
    'name' => 'Hex6_Admin_Created',
    'captain_id' => $contestantForAdmin->id,
]);
$adminReq->setUserResolver(fn () => $admin);
$adminTeamController->store($adminReq);

$adminTeam = Team::where('name', 'Hex6_Admin_Created')->first();
assertTest($adminTeam !== null, "Admin created team exists");
$adminCode = (string) $adminTeam?->invitation_code_encrypted;
assertTest(strlen($adminCode) === 6 && ctype_digit($adminCode), "Admin-created team code is exactly 6 numeric digits: '$adminCode'");

// 9. Verify UI Blade Input Attributes
echo "\n--- 9. Verifying UI Template ---\n";
$joinBlade = file_get_contents(resource_path('views/teams/join.blade.php'));
assertTest(strpos($joinBlade, 'pattern="[0-9]{6}"') !== false, "join.blade.php enforces pattern='[0-9]{6}'");
assertTest(strpos($joinBlade, 'minlength="6"') !== false, "join.blade.php specifies minlength='6'");
assertTest(strpos($joinBlade, 'maxlength="6"') !== false, "join.blade.php specifies maxlength='6'");
assertTest(strpos($joinBlade, 'inputmode="numeric"') !== false, "join.blade.php specifies inputmode='numeric'");

// Clean up test records
Team::where('name', 'like', 'Hex6_Test_%')->orWhere('name', 'Hex6_Admin_Created')->update(['captain_id' => null]);
TeamMembership::whereHas('user', fn($q) => $q->where('username', 'like', 'hex6_user_%'))->delete();
Team::where('name', 'like', 'Hex6_Test_%')->orWhere('name', 'Hex6_Admin_Created')->delete();
User::where('username', 'like', 'hex6_user_%')->delete();

echo "\n==========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "==========================================\n";

if ($failCount > 0) {
    exit(1);
}

