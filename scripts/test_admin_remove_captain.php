<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

echo "=== P4DC CTF: Test Admin Remove Captain and Team Members ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $condition, string $message): void {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $message\n";
        $passCount++;
    } else {
        echo "[FAIL] $message\n";
        $failCount++;
    }
}

// 1. Verify Primary Admin Integrity
$admin = User::where('username', 'admin')->first();
assertCondition($admin !== null && $admin->role === 'admin' && $admin->is_active, "Primary admin account exists and is active admin (ID: " . ($admin?->id ?? 'none') . ")");

// 2. Setup Test Accounts & Teams
// Clean up any previous test artifacts
Team::where('name', 'like', 'Test Team %')->update(['captain_id' => null]);
TeamMembership::whereHas('user', fn($q) => $q->where('username', 'like', 'test_mem_%'))->delete();
Team::where('name', 'like', 'Test Team %')->delete();
User::where('username', 'like', 'test_mem_%')->delete();

$testContestant1 = User::create([
    'username' => 'test_mem_capt1',
    'email' => 'test_mem_capt1@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$testContestant2 = User::create([
    'username' => 'test_mem_reg1',
    'email' => 'test_mem_reg1@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$testContestant3 = User::create([
    'username' => 'test_mem_reg2',
    'email' => 'test_mem_reg2@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$multiTeam = Team::create([
    'name' => 'Test Team Alpha',
    'captain_id' => $testContestant1->id,
    'status' => 'active',
    'invitation_code_hash' => hash('sha256', 'code1'),
    'invitation_code_encrypted' => 'code1',
]);

$m1 = TeamMembership::create([
    'team_id' => $multiTeam->id,
    'user_id' => $testContestant1->id,
    'role' => 'captain',
    'joined_at' => now()->subHours(3),
]);

$m2 = TeamMembership::create([
    'team_id' => $multiTeam->id,
    'user_id' => $testContestant2->id,
    'role' => 'member',
    'joined_at' => now()->subHours(2),
]);

$m3 = TeamMembership::create([
    'team_id' => $multiTeam->id,
    'user_id' => $testContestant3->id,
    'role' => 'member',
    'joined_at' => now()->subHours(1),
]);

// 3. Test Unauthorized Access (Contestant cannot use admin route)
echo "\n--- Testing Unauthorized Removal ---\n";
Auth::login($testContestant2);
$adminTeamController = app(\App\Http\Controllers\Admin\AdminTeamController::class);

// Direct controller invocation by contestant
$contestantReq = Request::create(route('admin.memberships.destroy', $m3), 'DELETE');
$contestantReq->setUserResolver(fn () => $testContestant2);
$controllerBlocked = false;
try {
    $adminTeamController->destroyMembership($contestantReq, $m3);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() === 403) {
        $controllerBlocked = true;
    }
}
assertCondition($controllerBlocked, "Direct controller invocation by contestant throws HTTP 403 Forbidden");

// Direct controller invocation by unauthenticated user
$unauthReq = Request::create(route('admin.memberships.destroy', $m3), 'DELETE');
$unauthReq->setUserResolver(fn () => null);
$unauthBlocked = false;
try {
    $adminTeamController->destroyMembership($unauthReq, $m3);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() === 403) {
        $unauthBlocked = true;
    }
}
assertCondition($unauthBlocked, "Direct controller invocation without authentication throws HTTP 403 Forbidden");

// HTTP Kernel stack testing
$httpKernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$routeReq = Request::create('/admin/memberships/' . $m3->id, 'DELETE');
$routeReq->setUserResolver(fn () => $testContestant2);
$routeResp = $httpKernel->handle($routeReq);
assertCondition(in_array($routeResp->getStatusCode(), [403, 302, 419]), "HTTP Kernel rejects unauthorized contestant access with status: " . $routeResp->getStatusCode());

// 4. Test Admin Removing a Regular Member
echo "\n--- Testing Admin Removing Regular Member ---\n";
Auth::login($admin);
$req = Request::create(route('admin.memberships.destroy', $m3), 'DELETE');
$req->setUserResolver(fn () => $admin);

$resp = $adminTeamController->destroyMembership($req, $m3);
assertCondition($resp->getStatusCode() === 302, "Membership destroy redirects with status message: " . session('status'));

$multiTeam->refresh();
assertCondition($multiTeam->captain_id === $testContestant1->id, "Team captain is unchanged (ID: {$multiTeam->captain_id})");
assertCondition(TeamMembership::where('id', $m3->id)->doesntExist(), "Regular member membership record was deleted");
assertCondition(User::where('id', $testContestant3->id)->exists(), "Contestant account was preserved");

$log = AdminAuditLog::where('action', 'team.membership.deleted')->where('subject_user_id', $testContestant3->id)->latest()->first();
assertCondition($log !== null && $log->actor_user_id === $admin->id, "Audit log created for regular member removal");

// 5. Test Admin Removing a Captain when eligible members remain
echo "\n--- Testing Admin Removing Captain with Remaining Member ---\n";
$reqCaptain = Request::create(route('admin.memberships.destroy', $m1), 'DELETE');
$reqCaptain->setUserResolver(fn () => $admin);

$respCaptain = $adminTeamController->destroyMembership($reqCaptain, $m1);
assertCondition($respCaptain->getStatusCode() === 302, "Captain removal redirects with status: " . session('status'));

$multiTeam->refresh();
assertCondition($multiTeam->captain_id === $testContestant2->id, "Captaincy automatically reassigned to oldest remaining member (test_mem_reg1, ID: {$testContestant2->id})");
assertCondition($multiTeam->status === 'active', "Team remains active");

$m2->refresh();
assertCondition($m2->role === 'captain', "Remaining member's role was promoted to 'captain'");
assertCondition(TeamMembership::where('id', $m1->id)->doesntExist(), "Former captain's membership record was deleted");
assertCondition(User::where('id', $testContestant1->id)->exists(), "Former captain's user account was preserved");

$reassignLog = AdminAuditLog::where('action', 'team.captain.reassigned')->where('subject_user_id', $testContestant2->id)->latest()->first();
assertCondition($reassignLog !== null && $reassignLog->actor_user_id === $admin->id, "Audit log created for captain reassignment");

$deleteCaptainMemLog = AdminAuditLog::where('action', 'team.membership.deleted')->where('subject_user_id', $testContestant1->id)->latest()->first();
assertCondition($deleteCaptainMemLog !== null && $deleteCaptainMemLog->details['was_captain'] === true, "Audit log created for captain membership deletion noting was_captain=true");

// 6. Test Admin Removing Captain when NO eligible members remain (sole member team)
echo "\n--- Testing Admin Removing Sole Captain ---\n";
$soleCaptain = User::create([
    'username' => 'test_mem_sole_capt',
    'email' => 'test_mem_sole_capt@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$soleTeam = Team::create([
    'name' => 'Test Team Solo',
    'captain_id' => $soleCaptain->id,
    'status' => 'active',
    'invitation_code_hash' => hash('sha256', 'code_solo'),
    'invitation_code_encrypted' => 'code_solo',
]);

$mSole = TeamMembership::create([
    'team_id' => $soleTeam->id,
    'user_id' => $soleCaptain->id,
    'role' => 'captain',
    'joined_at' => now(),
]);

$reqSole = Request::create(route('admin.memberships.destroy', $mSole), 'DELETE');
$reqSole->setUserResolver(fn () => $admin);

$respSole = $adminTeamController->destroyMembership($reqSole, $mSole);
assertCondition($respSole->getStatusCode() === 302, "Sole captain removal redirects with status: " . session('status'));

$soleTeam->refresh();
assertCondition($soleTeam->captain_id === null, "Sole team captain_id set to null");
assertCondition($soleTeam->status === 'inactive', "Sole team status set to inactive");
assertCondition(TeamMembership::where('id', $mSole->id)->doesntExist(), "Sole captain membership record deleted");
assertCondition(User::where('id', $soleCaptain->id)->exists(), "Sole captain user account preserved");

// 7. Test Removing Captain with Existing Solves (Preserving Solve Attribution)
echo "\n--- Testing Removing Captain with Historical Solves ---\n";
$solveUser = User::create([
    'username' => 'test_mem_solver',
    'email' => 'test_mem_solver@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$solveTeam = Team::create([
    'name' => 'Test Team Solver',
    'captain_id' => $solveUser->id,
    'status' => 'active',
    'invitation_code_hash' => hash('sha256', 'code_solve'),
    'invitation_code_encrypted' => 'code_solve',
]);

$mSolver = TeamMembership::create([
    'team_id' => $solveTeam->id,
    'user_id' => $solveUser->id,
    'role' => 'captain',
    'joined_at' => now(),
]);

// Add a dummy verified solve attributed to this team
$challenge = \App\Models\Challenge::first();
$solve = VerifiedSolve::create([
    'user_id' => $solveUser->id,
    'challenge_id' => $challenge ? $challenge->id : 1,
    'team_id_at_solve' => $solveTeam->id,
    'points_awarded' => 100,
    'flag_entered' => 'p4dCTF{test}',
    'verified_at' => now(),
]);

$reqSolver = Request::create(route('admin.memberships.destroy', $mSolver), 'DELETE');
$reqSolver->setUserResolver(fn () => $admin);

$respSolver = $adminTeamController->destroyMembership($reqSolver, $mSolver);
assertCondition($respSolver->getStatusCode() === 302, "Removal redirects with status: " . session('status'));

$mSolver->refresh();
assertCondition($mSolver->left_at !== null, "Membership retained with left_at timestamp to preserve historical solve attribution");
assertCondition(VerifiedSolve::where('id', $solve->id)->exists(), "Historical solve record preserved intact");

// Clean up dummy solve
$solve->delete();

// 8. Test Admin Protected Account Checks
echo "\n--- Testing Primary Admin Protection ---\n";
$adminUserController = app(\App\Http\Controllers\Admin\AdminUserController::class);
$reqAdminDel = Request::create(route('admin.users.destroy', $admin), 'DELETE');
$reqAdminDel->setUserResolver(fn () => $admin);

$caughtException = false;
try {
    $adminUserController->destroy($reqAdminDel, $admin);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    $caughtException = true;
    assertCondition($e->getStatusCode() === 403, "Deletion of primary admin threw 403 Forbidden");
}
assertCondition($caughtException, "Attempt to delete primary admin was blocked");
assertCondition(User::where('username', 'admin')->exists(), "Primary admin record remains intact");

// Clean up test data
Team::where('name', 'like', 'Test Team %')->update(['captain_id' => null]);
TeamMembership::whereHas('user', fn($q) => $q->where('username', 'like', 'test_mem_%'))->delete();
Team::where('name', 'like', 'Test Team %')->delete();
User::where('username', 'like', 'test_mem_%')->delete();

echo "\n==========================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "==========================================\n";

if ($failCount > 0) {
    exit(1);
}
