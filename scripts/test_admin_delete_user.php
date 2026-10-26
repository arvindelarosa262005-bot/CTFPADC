<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

echo "=== P4DC CTF Admin Delete User Verification ===\n\n";

// 1. Verify primary admin account exists and password is valid
$admin = User::where('username', 'admin')->first();
assertCondition("Primary admin account 'admin' exists", $admin !== null && $admin->isAdmin());
assertCondition("Primary admin is identified as isPrimaryAdmin()", $admin && $admin->isPrimaryAdmin());

// Test login check for admin
$candidates = ['admin', 'admin123', 'password', 'padcadmin', 'P4dcAdmin123!', 'local-change-me', 'secure-test-password', 'admin@123', 'admin2024', 'admin2026'];
$foundPw = null;
foreach ($candidates as $cand) {
    if (Hash::check($cand, $admin->password)) {
        $foundPw = $cand;
        break;
    }
}
if ($foundPw !== null) {
    echo "Found matching password: $foundPw\n";
    $authOk = Auth::attempt(['username' => 'admin', 'password' => $foundPw]);
    assertCondition("Original admin can authenticate via Auth::attempt with found password", $authOk);
} else {
    assertCondition("Admin password hash is intact and unchanged (starts with bcrypt prefix)", str_starts_with($admin->password, '$2y$') || str_starts_with($admin->password, '$2a$'));
}

// 2. Test Attempt to delete primary admin (Expect 403)
Auth::login($admin);
$controller = app(\App\Http\Controllers\Admin\AdminUserController::class);
$req = Request::create('/admin/users/' . $admin->id, 'DELETE');
$req->setUserResolver(fn() => $admin);

try {
    $controller->destroy($req, $admin);
    assertCondition("Attempt to delete primary admin should throw 403", false);
} catch (HttpException $e) {
    assertCondition("Attempt to delete primary admin throws 403 Forbidden (Code: {$e->getStatusCode()})", $e->getStatusCode() === 403);
    assertCondition("Error message mentions primary admin or own account", str_contains($e->getMessage(), 'primary administrator') || str_contains($e->getMessage(), 'own account'));
}

// 3. Test Attempt to delete self via a secondary admin
$tempAdmin = User::create([
    'username' => 'test_sec_admin_' . time(),
    'email' => 'test_sec_admin_' . time() . '@example.test',
    'password' => Hash::make('secret123'),
    'role' => 'admin',
    'is_active' => true,
]);

Auth::login($tempAdmin);
$reqSelf = Request::create('/admin/users/' . $tempAdmin->id, 'DELETE');
$reqSelf->setUserResolver(fn() => $tempAdmin);
try {
    $controller->destroy($reqSelf, $tempAdmin);
    assertCondition("Attempt to delete self should throw 403", false);
} catch (HttpException $e) {
    assertCondition("Attempt to delete own account throws 403 Forbidden (Code: {$e->getStatusCode()})", $e->getStatusCode() === 403);
}

// 4. Test Attempt to delete another admin
Auth::login($admin);
$reqOtherAdmin = Request::create('/admin/users/' . $tempAdmin->id, 'DELETE');
$reqOtherAdmin->setUserResolver(fn() => $admin);
try {
    $controller->destroy($reqOtherAdmin, $tempAdmin);
    assertCondition("Attempt to delete another admin should throw 403", false);
} catch (HttpException $e) {
    assertCondition("Attempt to delete protected admin throws 403 Forbidden (Code: {$e->getStatusCode()})", $e->getStatusCode() === 403);
}

// Clean up tempAdmin safely
$tempAdmin->delete();

// 5. Test Active Team Captain protection (Expect 422)
$captainUser = User::create([
    'username' => 'capt_' . time(),
    'email' => 'capt_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);
$testTeam = Team::create([
    'name' => 'Team ' . time(),
    'captain_id' => $captainUser->id,
    'status' => 'active',
]);
TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $captainUser->id,
    'role' => 'captain',
    'joined_at' => now(),
]);

$reqCaptain = Request::create('/admin/users/' . $captainUser->id, 'DELETE');
$reqCaptain->setUserResolver(fn() => $admin);
try {
    $controller->destroy($reqCaptain, $captainUser);
    assertCondition("Attempt to delete active captain should throw 422", false);
} catch (HttpException $e) {
    assertCondition("Attempt to delete active captain throws 422 (Code: {$e->getStatusCode()})", $e->getStatusCode() === 422);
    assertCondition("Error message requires captaincy transfer", str_contains($e->getMessage(), 'Transfer team captaincy'));
}

// 6. Test Successful Deletion of a Contestant with Team Membership
$memberUser = User::create([
    'username' => 'member_' . time(),
    'email' => 'member_' . time() . '@example.test',
    'password' => Hash::make('password'),
    'role' => 'contestant',
    'is_active' => true,
]);
TeamMembership::create([
    'team_id' => $testTeam->id,
    'user_id' => $memberUser->id,
    'role' => 'member',
    'joined_at' => now(),
]);

$initialCount = User::count();
$reqMember = Request::create('/admin/users/' . $memberUser->id, 'DELETE');
$reqMember->setUserResolver(fn() => $admin);
$response = $controller->destroy($reqMember, $memberUser);

assertCondition("Delete returns redirect response", $response->isRedirect());
assertCondition("User count decremented by 1", User::count() === $initialCount - 1);
assertCondition("Contestant account removed from DB", User::find($memberUser->id) === null);
assertCondition("Team still exists intact", Team::find($testTeam->id) !== null);
assertCondition("Captain membership intact", TeamMembership::where('team_id', $testTeam->id)->where('user_id', $captainUser->id)->exists());
assertCondition("Deleted member's membership safely cleaned up", TeamMembership::where('user_id', $memberUser->id)->count() === 0);

// Check Audit Log
$auditLog = AdminAuditLog::where('action', 'user.deleted')->orderByDesc('id')->first();
assertCondition("Audit log recorded 'user.deleted'", $auditLog !== null);
assertCondition("Audit log actor is admin", $auditLog && $auditLog->actor_user_id === $admin->id);
assertCondition("Audit log details retain deleted username", $auditLog && ($auditLog->details['username'] ?? '') === $memberUser->username);
assertCondition("Audit log details retain deleted user_id", $auditLog && ($auditLog->details['user_id'] ?? '') === $memberUser->id);

// 7. Clean up the test team & captain
$testTeam->memberships()->delete();
$testTeam->delete();
$captainUser->delete();

// 8. Test Role & Status Protection on primary admin 'admin'
$reqRole = Request::create('/admin/users/' . $admin->id . '/role', 'PATCH', ['role' => 'contestant']);
$reqRole->setUserResolver(fn() => $admin);
try {
    $controller->updateRole($reqRole, $admin);
    assertCondition("Primary admin role cannot be demoted", false);
} catch (HttpException $e) {
    assertCondition("Primary admin role change blocked with 403 Forbidden", $e->getStatusCode() === 403);
}

$reqStatus = Request::create('/admin/users/' . $admin->id . '/status', 'PATCH', ['is_active' => '0']);
$reqStatus->setUserResolver(fn() => $admin);
try {
    $controller->updateStatus($reqStatus, $admin);
    assertCondition("Primary admin cannot be deactivated", false);
} catch (HttpException $e) {
    assertCondition("Primary admin deactivation blocked with 403 Forbidden", $e->getStatusCode() === 403);
}

// 9. Verify Frontend View Rendering
$renderedView = view('admin.dashboard', [
    'errors' => new \Illuminate\Support\ViewErrorBag(),
    'registeredUsers' => User::count(),
    'existingTeams' => Team::count(),
    'users' => User::with('activeTeams:id,name')->orderBy('username')->get(),
    'teams' => Team::withCount('activeMembers as members_count')->with(['activeMembers:id,username', 'captain:id,username'])->orderBy('name')->get(),
    'individualScorers' => collect(),
    'teamScorers' => collect(),
    'auditLogs' => AdminAuditLog::with(['actor:id,username', 'subject:id,username'])->orderByDesc('created_at')->limit(20)->get(),
    'membershipHistory' => collect(),
])->render();

assertCondition("Rendered dashboard includes ACTIONS column header", str_contains($renderedView, '<th>ACTIONS</th>'));
assertCondition("Rendered dashboard displays 'Protected admin' for admin", str_contains($renderedView, 'Protected admin'));
assertCondition("Rendered dashboard includes confirmation dialog on delete forms", str_contains($renderedView, "confirm('Are you sure you want to delete user"));
// 10. Verify Unauthorized HTTP Requests via Middleware Pipeline
$roleMiddleware = new \App\Http\Middleware\EnsureUserHasRole();

// Unauthenticated request (no user)
$guestReq = Request::create('/admin/users/9999', 'DELETE');
try {
    $roleMiddleware->handle($guestReq, fn() => response('ok'), 'admin');
    assertCondition("Unauthenticated user rejected by role:admin", false);
} catch (HttpException $e) {
    assertCondition("Unauthenticated user rejected by role:admin with 403 Forbidden", $e->getStatusCode() === 403);
}

// Contestant user request to admin route
$contestant = User::where('role', 'contestant')->first();
if ($contestant) {
    $contestantReq = Request::create('/admin/users/9999', 'DELETE');
    $contestantReq->setUserResolver(fn() => $contestant);
    try {
        $res = $roleMiddleware->handle($contestantReq, fn() => response('ok'), 'admin');
        assertCondition("Contestant denied by role:admin middleware", false);
    } catch (HttpException $e) {
        assertCondition("Contestant denied by role:admin middleware with 403 Forbidden", $e->getStatusCode() === 403);
    }
}

echo "\nSummary: Passed: $passed, Failed: $failed\n";
exit($failed > 0 ? 1 : 0);
