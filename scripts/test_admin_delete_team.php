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
use Illuminate\Support\Facades\DB;

echo "=== P4DC CTF: Test Admin Delete Team Action ===\n\n";

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
$originalAdminCount = User::where('role', 'admin')->count();

// Clean up any previous test artifacts
Team::where('name', 'like', 'DeleteTest_%')->update(['captain_id' => null]);
TeamMembership::whereHas('user', fn($q) => $q->where('username', 'like', 'deltest_%'))->delete();
VerifiedSolve::whereHas('user', fn($q) => $q->where('username', 'like', 'deltest_%'))->delete();
Team::where('name', 'like', 'DeleteTest_%')->delete();
User::where('username', 'like', 'deltest_%')->delete();

// Setup test users
$captain = User::create([
    'username' => 'deltest_captain',
    'email' => 'deltest_captain@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$member = User::create([
    'username' => 'deltest_member',
    'email' => 'deltest_member@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$unauthorizedContestant = User::create([
    'username' => 'deltest_other',
    'email' => 'deltest_other@example.com',
    'password' => bcrypt('password123'),
    'role' => 'contestant',
    'is_active' => true,
]);

$invitationCode = Team::generateInvitationCode();
$testTeam = Team::create([
    'name' => 'DeleteTest_Team Alpha',
    'captain_id' => $captain->id,
    'invitation_code_hash' => hash('sha256', $invitationCode),
    'invitation_code_encrypted' => $invitationCode,
    'status' => 'active',
]);

TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $captain->id,
    'role' => 'captain',
    'joined_at' => now()->subDays(2),
]);

TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $member->id,
    'role' => 'member',
    'joined_at' => now()->subDay(),
]);

// Find or create a challenge to test solve preservation
$challenge = Challenge::first();
if (!$challenge) {
    $challenge = Challenge::create([
        'title' => 'Test Stego Flag',
        'slug' => 'test-stego-flag',
        'category' => 'Steganography',
        'difficulty' => 'Medium',
        'points' => 250,
        'flag' => 'p4dCTF{del_test_flag}',
        'published_at' => now(),
    ]);
}

$solve = VerifiedSolve::create([
    'challenge_id' => $challenge->id,
    'user_id' => $member->id,
    'team_id_at_solve' => $testTeam->id,
    'points_awarded' => 250,
    'verified_at' => now(),
]);

assertCondition(Team::where('id', $testTeam->id)->exists(), "Test team created with ID: {$testTeam->id}");
assertCondition(TeamMembership::where('team_id', $testTeam->id)->count() === 2, "Test team has 2 memberships (captain and member)");
assertCondition(VerifiedSolve::where('team_id_at_solve', $testTeam->id)->count() === 1, "Verified solve attributed to test team exists");

// 2. Test Authorization: Contestant attempting deletion must be rejected (403)
echo "\n--- Testing Authorization ---\n";
Auth::login($unauthorizedContestant);
$request = Request::create(route('admin.teams.destroy', $testTeam), 'DELETE');
$request->setUserResolver(fn() => $unauthorizedContestant);

$caughtForbidden = false;
try {
    $controller = app(\App\Http\Controllers\Admin\AdminTeamController::class);
    $controller->destroy($request, $testTeam);
} catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
    if ($e->getStatusCode() === 403) {
        $caughtForbidden = true;
    }
}
assertCondition($caughtForbidden, "Contestant is rejected with HTTP 403 Forbidden when calling destroy");
assertCondition(Team::where('id', $testTeam->id)->exists(), "Test team was not deleted by unauthorized contestant");

// 3. Test Admin Team Deletion
echo "\n--- Testing Admin Team Deletion ---\n";
Auth::login($admin);
$request = Request::create(route('admin.teams.destroy', $testTeam), 'DELETE');
$request->setUserResolver(fn() => $admin);

$controller = app(\App\Http\Controllers\Admin\AdminTeamController::class);
$response = $controller->destroy($request, $testTeam);

assertCondition($response->isRedirect(route('admin.dashboard')), "Admin deletion redirects to admin dashboard");
assertCondition(!Team::where('id', $testTeam->id)->exists(), "Team '{$testTeam->name}' was deleted from teams table");
assertCondition(TeamMembership::where('team_id', $testTeam->id)->count() === 0, "Team memberships for team {$testTeam->id} were removed");

// 4. Verify Contestant Accounts Preserved
echo "\n--- Testing Integrity of Users, Scores, and History ---\n";
$captainAfter = User::find($captain->id);
$memberAfter = User::find($member->id);
assertCondition($captainAfter !== null && $captainAfter->username === 'deltest_captain', "Captain user account is preserved");
assertCondition($memberAfter !== null && $memberAfter->username === 'deltest_member', "Member user account is preserved");

// 5. Verify Solve Integrity (points and solve preserved, team_id unlinked)
$solveAfter = VerifiedSolve::find($solve->id);
assertCondition($solveAfter !== null, "Verified solve record is preserved");
assertCondition($solveAfter?->user_id === $member->id, "Verified solve solver user_id remains untouched");
assertCondition($solveAfter?->points_awarded === 250, "Verified solve points_awarded remains untouched (250 points)");
assertCondition($solveAfter?->team_id_at_solve === null, "Verified solve team_id_at_solve safely set to null");

// 6. Verify Admin Audit Log
$auditLog = AdminAuditLog::where('action', 'team.deleted')->latest()->first();
assertCondition($auditLog !== null, "Audit log record for 'team.deleted' exists");
assertCondition($auditLog?->actor_user_id === $admin->id, "Audit log records admin as actor (ID: {$admin->id})");
assertCondition(($auditLog?->details['name'] ?? '') === 'DeleteTest_Team Alpha', "Audit log records deleted team name in details: " . ($auditLog?->details['name'] ?? 'none'));

// 7. Verify Admin Account Protection
$adminAfter = User::where('username', 'admin')->first();
assertCondition($adminAfter !== null && $adminAfter->id === $admin->id, "Primary admin account remains intact");
assertCondition(User::where('role', 'admin')->count() === $originalAdminCount, "Admin account count unchanged ({$originalAdminCount})");

// 8. Test UI Rendering of Admin Dashboard
echo "\n--- Testing UI Rendering in Admin Dashboard ---\n";
// Create a new team to check Blade table rendering
$viewTeam = Team::create([
    'name' => 'DeleteTest_View Team',
    'captain_id' => $captain->id,
    'invitation_code_hash' => hash('sha256', '123456'),
    'invitation_code_encrypted' => '123456',
    'status' => 'active',
]);
TeamMembership::create([
    'team_id' => $viewTeam->id,
    'user_id' => $captain->id,
    'role' => 'captain',
    'joined_at' => now(),
]);

Auth::login($admin);
\Illuminate\Support\Facades\View::share('errors', new \Illuminate\Support\ViewErrorBag());
$dashboardResponse = app(\App\Http\Controllers\Admin\AdminDashboardController::class)->index();
$renderedHtml = $dashboardResponse->render();

$hasActionsHeader = str_contains($renderedHtml, '<th>ACTIONS</th>');
$hasDeleteForm = str_contains($renderedHtml, route('admin.teams.destroy', $viewTeam));
$hasConfirmation = str_contains($renderedHtml, "Are you sure you want to delete team \'DeleteTest_View Team\'? This action cannot be undone.");
$hasDeleteButton = str_contains($renderedHtml, 'Delete</button>') && str_contains($renderedHtml, 'small-action-danger');

assertCondition($hasActionsHeader, "Teams table contains <th>ACTIONS</th> header");
assertCondition($hasDeleteForm, "Teams table contains form pointing to admin.teams.destroy for team");
assertCondition($hasConfirmation, "Delete form has onsubmit confirmation dialog displaying team name");
assertCondition($hasDeleteButton, "Delete button has classes 'small-action small-action-danger'");

// Clean up
$viewTeam->update(['captain_id' => null]);
TeamMembership::where('team_id', $viewTeam->id)->delete();
$viewTeam->delete();
$solve->delete();
$captain->delete();
$member->delete();
$unauthorizedContestant->delete();
AdminAuditLog::where('action', 'team.deleted')->where('details->name', 'like', 'DeleteTest_%')->delete();

echo "\n========================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "========================================\n";

if ($failCount > 0) {
    exit(1);
}
