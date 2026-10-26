<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminAuditLog;
use App\Models\Challenge;
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

echo "=== P4DC CTF Admin Delete Solves Verification ===\n\n";

$admin = User::where('username', 'admin')->first();
assertCondition("Primary admin account exists", $admin !== null);

$controller = app(\App\Http\Controllers\Admin\AdminUserController::class);

// 1. Backend protection: Attempt to delete solves for primary admin 'admin'
Auth::login($admin);
$reqAdmin = Request::create('/admin/users/' . $admin->id . '/solves', 'DELETE');
$reqAdmin->setUserResolver(fn() => $admin);

try {
    $controller->destroySolves($reqAdmin, $admin);
    assertCondition("Attempt to delete primary admin solves throws 403 Forbidden", false);
} catch (HttpException $e) {
    assertCondition("Attempt to delete primary admin solves throws 403 Forbidden (Code: {$e->getStatusCode()})", $e->getStatusCode() === 403);
    assertCondition("Error message mentions primary administrator records protected", str_contains($e->getMessage(), 'primary administrator records are protected'));
}

// 2. Authorization check: Role middleware rejects unauthenticated and contestants
$roleMiddleware = new \App\Http\Middleware\EnsureUserHasRole();

$guestReq = Request::create('/admin/users/9999/solves', 'DELETE');
try {
    $roleMiddleware->handle($guestReq, fn() => response('ok'), 'admin');
    assertCondition("Guest rejected by role:admin", false);
} catch (HttpException $e) {
    assertCondition("Guest rejected by role:admin with 403 Forbidden", $e->getStatusCode() === 403);
}

$contestant = User::where('role', 'contestant')->first();
if ($contestant) {
    $contestantReq = Request::create('/admin/users/9999/solves', 'DELETE');
    $contestantReq->setUserResolver(fn() => $contestant);
    try {
        $roleMiddleware->handle($contestantReq, fn() => response('ok'), 'admin');
        assertCondition("Contestant rejected by role:admin", false);
    } catch (HttpException $e) {
        assertCondition("Contestant rejected by role:admin with 403 Forbidden", $e->getStatusCode() === 403);
    }
}

// 3. Create test challenges if needed
$chal1 = Challenge::firstOrCreate(
    ['slug' => 'test-score-chal-1'],
    ['title' => 'Score Test 1', 'category' => 'Web', 'difficulty' => 'easy', 'points' => 50, 'published_at' => now()]
);
$chal2 = Challenge::firstOrCreate(
    ['slug' => 'test-score-chal-2'],
    ['title' => 'Score Test 2', 'category' => 'Crypto', 'difficulty' => 'medium', 'points' => 100, 'published_at' => now()]
);

// 4. Create test team and contestants
$testTeam = Team::create([
    'name' => 'Score Test Team ' . time(),
    'status' => 'active',
]);

$userA = User::create([
    'username' => 'scorer_a_' . time(),
    'email' => 'scorer_a_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);

$userB = User::create([
    'username' => 'scorer_b_' . time(),
    'email' => 'scorer_b_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);

$userC = User::create([
    'username' => 'scorer_c_' . time(),
    'email' => 'scorer_c_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);

TeamMembership::create(['team_id' => $testTeam->id, 'user_id' => $userA->id, 'role' => 'member', 'joined_at' => now()]);
TeamMembership::create(['team_id' => $testTeam->id, 'user_id' => $userB->id, 'role' => 'member', 'joined_at' => now()]);

// Create verified solves:
// User A: Chal 1 (50 pts, attributed to Team)
VerifiedSolve::create([
    'challenge_id' => $chal1->id,
    'user_id' => $userA->id,
    'team_id_at_solve' => $testTeam->id,
    'points_awarded' => 50,
    'verified_at' => now(),
]);

// User B: Chal 2 (100 pts, attributed to Team)
VerifiedSolve::create([
    'challenge_id' => $chal2->id,
    'user_id' => $userB->id,
    'team_id_at_solve' => $testTeam->id,
    'points_awarded' => 100,
    'verified_at' => now(),
]);

// User C: Chal 1 (50 pts, no team)
VerifiedSolve::create([
    'challenge_id' => $chal1->id,
    'user_id' => $userC->id,
    'team_id_at_solve' => null,
    'points_awarded' => 50,
    'verified_at' => now(),
]);

// Verify initial scoring state
$teamScoreBefore = $testTeam->attributedSolves()->sum('points_awarded');
assertCondition("Team Alpha initial score is 150 pts", $teamScoreBefore === 150);
$userAScoreBefore = $userA->verifiedSolves()->sum('points_awarded');
assertCondition("User A initial score is 50 pts", $userAScoreBefore === 50);

// 5. Execute Delete Solves for User A
Auth::login($admin);
$reqDelA = Request::create('/admin/users/' . $userA->id . '/solves', 'DELETE');
$reqDelA->setUserResolver(fn() => $admin);

$res = $controller->destroySolves($reqDelA, $userA);
assertCondition("destroySolves returns redirect response", $res->isRedirect());

// 6. Verify Solves and Leaderboard recalculation
$userASolvesAfter = $userA->verifiedSolves()->count();
assertCondition("User A verified solves count is now 0", $userASolvesAfter === 0);
assertCondition("User A personal points is 0", (int) $userA->verifiedSolves()->sum('points_awarded') === 0);

// Verify User B and User C solves are unaffected
assertCondition("User B solves remain intact (1 solve, 100 pts)", $userB->verifiedSolves()->count() === 1 && (int) $userB->verifiedSolves()->sum('points_awarded') === 100);
assertCondition("User C solves remain intact (1 solve, 50 pts)", $userC->verifiedSolves()->count() === 1 && (int) $userC->verifiedSolves()->sum('points_awarded') === 50);

// Verify Team Score consistency
$teamScoreAfter = $testTeam->attributedSolves()->sum('points_awarded');
assertCondition("Team Alpha score adjusted consistently to 100 pts (from User B)", $teamScoreAfter === 100);
assertCondition("Team Alpha attributed solves count is 1", $testTeam->attributedSolves()->count() === 1);

// Verify User A still exists as an account (only solves removed, not user)
assertCondition("User A account still exists", User::find($userA->id) !== null);

// 7. Verify Audit Log entry
$auditLog = AdminAuditLog::where('action', 'user.solves.deleted')->orderByDesc('id')->first();
assertCondition("Audit log recorded 'user.solves.deleted'", $auditLog !== null);
assertCondition("Audit log records acting admin", $auditLog && $auditLog->actor_user_id === $admin->id);
assertCondition("Audit log records affected user", $auditLog && $auditLog->subject_user_id === $userA->id);
assertCondition("Audit log records solves_removed = 1", $auditLog && ($auditLog->details['solves_removed'] ?? 0) === 1);
assertCondition("Audit log records points_removed = 50", $auditLog && ($auditLog->details['points_removed'] ?? 0) === 50);

// 8. Verify Frontend View Rendering for Individual Top Scorers table
$individualScorers = User::whereHas('verifiedSolves')
    ->withSum('verifiedSolves as total_verified_points', 'points_awarded')
    ->withCount('verifiedSolves as challenges_solved')
    ->orderByDesc('total_verified_points')
    ->orderBy('username')
    ->get();

$renderedView = view('admin.dashboard', [
    'errors' => new \Illuminate\Support\ViewErrorBag(),
    'registeredUsers' => User::count(),
    'existingTeams' => Team::count(),
    'users' => User::with('activeTeams:id,name')->orderBy('username')->get(),
    'teams' => Team::withCount('activeMembers as members_count')->with(['activeMembers:id,username', 'captain:id,username'])->orderBy('name')->get(),
    'individualScorers' => $individualScorers,
    'teamScorers' => collect(),
    'auditLogs' => AdminAuditLog::with(['actor:id,username', 'subject:id,username'])->orderByDesc('created_at')->limit(20)->get(),
    'membershipHistory' => collect(),
])->render();

assertCondition("Rendered individual rankings includes ACTIONS header", str_contains($renderedView, '<th>ACTIONS</th>'));
assertCondition("Rendered individual rankings shows Delete action for User B", str_contains($renderedView, route('admin.users.solves.destroy', $userB)));
assertCondition("Rendered individual rankings confirmation includes username", str_contains($renderedView, $userB->username));
assertCondition("Rendered individual rankings confirmation includes solves and points", str_contains($renderedView, '1 solve(s) and 100 points'));

// 9. Clean up test data
VerifiedSolve::whereIn('user_id', [$userA->id, $userB->id, $userC->id])->delete();
$testTeam->memberships()->delete();
$testTeam->delete();
$userA->delete();
$userB->delete();
$userC->delete();
$chal1->delete();
$chal2->delete();

// 10. Verify primary admin account integrity
$admin->refresh();
assertCondition("Primary admin account intact", $admin->id === 44 && $admin->role === 'admin' && $admin->is_active);

echo "\nSummary: Passed: $passed, Failed: $failed\n";
exit($failed > 0 ? 1 : 0);

