<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminAuditLog;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

$passed = 0;
$failed = 0;

function assertCondition($desc, $cond) {
    global $passed, $failed;
    if ($cond) {
        echo "[PASS] $desc\n";
        $passed++;
    } else {
        echo "[FAIL] $desc\n";
        $failed++;
    }
}

echo "=== P4DC CTF Admin Membership Deletion & Scoring Reset Verification ===\n\n";

$admin = User::where('username', 'admin')->first();
assertCondition("Primary admin account exists", $admin !== null);
Auth::login($admin);

$teamController = app(\App\Http\Controllers\Admin\AdminTeamController::class);
$scoreController = app(\App\Http\Controllers\Admin\AdminScoreController::class);
$roleMiddleware = new \App\Http\Middleware\EnsureUserHasRole();

// 1. Authorization checks for new endpoints
$guestReq = Request::create('/admin/scores/recalculate', 'POST');
try {
    $roleMiddleware->handle($guestReq, fn() => response('ok'), 'admin');
    assertCondition("Guest rejected from recalculate", false);
} catch (HttpException $e) {
    assertCondition("Guest rejected from recalculate with 403 Forbidden", $e->getStatusCode() === 403);
}

$contestant = User::where('role', 'contestant')->first();
if ($contestant) {
    $contestantReq = Request::create('/admin/scores/reset', 'POST');
    $contestantReq->setUserResolver(fn() => $contestant);
    try {
        $roleMiddleware->handle($contestantReq, fn() => response('ok'), 'admin');
        assertCondition("Contestant rejected from score reset", false);
    } catch (HttpException $e) {
        assertCondition("Contestant rejected from score reset with 403 Forbidden", $e->getStatusCode() === 403);
    }
}

// 2. Test Membership History Actions
$testTeam = Team::create([
    'name' => 'Membership Test Team ' . time(),
    'status' => 'active',
]);

$captainUser = User::create([
    'username' => 'capt_mem_' . time(),
    'email' => 'capt_mem_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);

$memberUser1 = User::create([
    'username' => 'mem1_' . time(),
    'email' => 'mem1_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);

$memberUser2 = User::create([
    'username' => 'mem2_' . time(),
    'email' => 'mem2_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);

$captainMembership = TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $captainUser->id,
    'role' => 'captain',
    'joined_at' => now(),
]);
$testTeam->update(['captain_id' => $captainUser->id]);

$mem1 = TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $memberUser1->id,
    'role' => 'member',
    'joined_at' => now(),
]);

$mem2 = TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $memberUser2->id,
    'role' => 'member',
    'joined_at' => now(),
    'left_at' => now(),
]);

// 2a. Attempt to remove active captain membership (should fail with 422)
$reqCapt = Request::create('/admin/memberships/' . $captainMembership->id, 'DELETE');
$reqCapt->setUserResolver(fn() => $admin);
try {
    $teamController->destroyMembership($reqCapt, $captainMembership);
    assertCondition("Active captain membership removal should fail with 422", false);
} catch (HttpException $e) {
    assertCondition("Active captain membership removal rejected with 422", $e->getStatusCode() === 422);
}

// 2b. Remove inactive membership mem2 (no solves): should be deleted
$reqMem2 = Request::create('/admin/memberships/' . $mem2->id, 'DELETE');
$reqMem2->setUserResolver(fn() => $admin);
$res2 = $teamController->destroyMembership($reqMem2, $mem2);
assertCondition("destroyMembership returns redirect response", $res2->isRedirect());
assertCondition("Inactive membership record was deleted", TeamMembership::find($mem2->id) === null);
assertCondition("Member account mem2 still exists", User::find($memberUser2->id) !== null);
assertCondition("Team still exists", Team::find($testTeam->id) !== null);

// 2c. Membership with solves: create solve for memberUser1
$testChal = Challenge::firstOrCreate(
    ['slug' => 'test-mem-chal'],
    ['title' => 'Mem Test Chal', 'category' => 'Web', 'difficulty' => 'easy', 'points' => 75, 'published_at' => now()]
);
VerifiedSolve::create([
    'challenge_id' => $testChal->id,
    'user_id' => $memberUser1->id,
    'team_id_at_solve' => $testTeam->id,
    'points_awarded' => 75,
    'verified_at' => now(),
]);

$reqMem1 = Request::create('/admin/memberships/' . $mem1->id, 'DELETE');
$reqMem1->setUserResolver(fn() => $admin);
$res1 = $teamController->destroyMembership($reqMem1, $mem1);
assertCondition("destroyMembership for member with solves returns redirect", $res1->isRedirect());
$freshMem1 = TeamMembership::find($mem1->id);
assertCondition("Active membership with solves was safely marked inactive (left_at set)", $freshMem1 !== null && $freshMem1->left_at !== null);
assertCondition("Audit log recorded membership deactivation", AdminAuditLog::where('action', 'team.membership.deactivated')->exists());

// 3. Test Score Recalculation
// Introduce a point drift to test recalculation reconciliation
$solveRow = VerifiedSolve::where('challenge_id', $testChal->id)->firstOrFail();
$solveRow->update(['points_awarded' => 10]); // drifted from 75

$reqRecalc = Request::create('/admin/scores/recalculate', 'POST');
$reqRecalc->setUserResolver(fn() => $admin);
$resRecalc = $scoreController->recalculate($reqRecalc);

assertCondition("recalculate returns redirect", $resRecalc->isRedirect());
$solveRow->refresh();
assertCondition("Solve points reconciled to canonical challenge points (75)", $solveRow->points_awarded === 75);
assertCondition("Audit log recorded 'scores.recalculated'", AdminAuditLog::where('action', 'scores.recalculated')->exists());

// 4. Test Score Reset
// Add a challenge submission
ChallengeSubmission::create([
    'user_id' => $memberUser1->id,
    'challenge_id' => $testChal->id,
    'submission_hash' => hash('sha256', 'test_flag'),
    'is_correct' => true,
]);

$initialChalCount = Challenge::count();
$initialUserCount = User::count();
$initialTeamCount = Team::count();

$reqReset = Request::create('/admin/scores/reset', 'POST');
$reqReset->setUserResolver(fn() => $admin);
$resReset = $scoreController->reset($reqReset);

assertCondition("reset returns redirect", $resReset->isRedirect());
assertCondition("Verified solves count is 0", VerifiedSolve::count() === 0);
assertCondition("Challenge submissions count is 0", ChallengeSubmission::count() === 0);
assertCondition("Challenge definitions preserved", Challenge::count() === $initialChalCount);
assertCondition("Test challenge still exists intact", Challenge::where('slug', 'test-mem-chal')->exists());
assertCondition("User accounts preserved", User::count() === $initialUserCount);
assertCondition("Teams preserved", Team::count() === $initialTeamCount);
assertCondition("Audit log recorded 'competition.solves_reset'", AdminAuditLog::where('action', 'competition.solves_reset')->exists());

// 5. Test Frontend View Rendering for Section 04 and Section 05
$renderedView = view('admin.dashboard', [
    'errors' => new \Illuminate\Support\ViewErrorBag(),
    'registeredUsers' => User::count(),
    'existingTeams' => Team::count(),
    'users' => User::with('activeTeams:id,name')->orderBy('username')->get(),
    'teams' => Team::withCount('activeMembers as members_count')->with(['activeMembers:id,username', 'captain:id,username'])->orderBy('name')->get(),
    'individualScorers' => collect(),
    'teamScorers' => collect(),
    'auditLogs' => AdminAuditLog::with(['actor:id,username', 'subject:id,username'])->orderByDesc('created_at')->limit(20)->get(),
    'membershipHistory' => TeamMembership::with(['team:id,name', 'user:id,username'])->orderByDesc('joined_at')->limit(50)->get(),
])->render();

assertCondition("Rendered dashboard includes ACTIONS column in Membership history", str_contains($renderedView, '<th>ACTIONS</th>'));
assertCondition("Rendered dashboard includes confirmation dialog with member, team, and role", str_contains($renderedView, "remove the membership record for member"));
assertCondition("Rendered dashboard includes Recalculate Scores button", str_contains($renderedView, 'Recalculate Scores'));
assertCondition("Rendered dashboard includes Reset Test Solves button", str_contains($renderedView, 'Reset Test Solves'));
assertCondition("Rendered dashboard includes Reset confirmation warning", str_contains($renderedView, 'WARNING: Reset all test solves'));

// 6. Clean up test records
$testTeam->memberships()->delete();
$testTeam->delete();
$captainUser->delete();
$memberUser1->delete();
$memberUser2->delete();
$testChal->delete();

// 7. Verify Primary Admin intact
$admin->refresh();
assertCondition("Primary admin account intact", $admin->id === 44 && $admin->role === 'admin' && $admin->is_active);

echo "\nSummary: Passed: $passed, Failed: $failed\n";
exit($failed > 0 ? 1 : 0);

