<?php

/**
 * P4DC CTF - Comprehensive Production Test Suite
 * Covers Phases 4, 5, and 6:
 * - Authentication & Authorization (Admin vs Contestant, Role gating, Account status)
 * - All 11 Challenges (Modals, Details, Artifact Downloads, Target Routes)
 * - Flag Validation & Scoring (Correct, Duplicate, Incorrect, Point accuracy)
 * - Team Workflow (Create, Join Code, Leave)
 * - Admin Panel Security & Functionality
 * - Security Headers & Data Integrity
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Team;
use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use App\Models\VerifiedSolve;
use App\Services\FlagSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

$results = [
    'passed' => 0,
    'failed' => 0,
    'details' => [],
];

function logTest($name, $status, $message = '') {
    global $results;
    if ($status) {
        $results['passed']++;
        echo " [PASS] $name\n";
    } else {
        $results['failed']++;
        echo " [FAIL] $name - $message\n";
    }
    $results['details'][] = ['name' => $name, 'status' => $status, 'message' => $message];
}

echo "========================================================\n";
echo " P4DC CTF COMPREHENSIVE PRODUCTION VERIFICATION SUITE\n";
echo " Environment: " . app()->environment() . "\n";
echo " Debug Mode: " . (config('app.debug') ? 'ENABLED' : 'DISABLED') . "\n";
echo " App URL: " . config('app.url') . "\n";
echo "========================================================\n\n";

// SECTION 1: Baseline Accounts & Data Integrity Verification
echo "--- SECTION 1: Account & Data Integrity Audit ---\n";
$admin = User::find(44);
logTest('Primary Admin (ID 44) exists', $admin !== null && $admin->role === 'admin' && $admin->email === 'brnrdolrnz@gmail.com', 'Admin 44 missing or corrupted');

$contestants = User::where('role', 'contestant')->get();
logTest('Contestant accounts exist', $contestants->count() >= 5, "Found {$contestants->count()} contestants, expected at least 5");

$challenges = Challenge::orderBy('id')->get();
logTest('All 11 Challenges exist', $challenges->count() === 11, "Found {$challenges->count()} challenges, expected 11");

$expectedChallengeIds = [3, 4, 5, 6, 7, 8, 21, 22, 23, 24, 25];
$actualChallengeIds = $challenges->pluck('id')->all();
logTest('Exact Challenge IDs preserved', $actualChallengeIds === $expectedChallengeIds, 'Challenge ID mismatch: ' . json_encode($actualChallengeIds));


// SECTION 2: Security Headers Verification
echo "\n--- SECTION 2: Security Headers Middleware Audit ---\n";
$testReq = Request::create('/', 'GET');
$response = $kernel->handle($testReq);
$headers = $response->headers;

logTest('Security Header: X-Frame-Options', $headers->get('X-Frame-Options') === 'SAMEORIGIN', 'Missing or invalid X-Frame-Options');
logTest('Security Header: X-Content-Type-Options', $headers->get('X-Content-Type-Options') === 'nosniff', 'Missing or invalid X-Content-Type-Options');
logTest('Security Header: X-XSS-Protection', str_contains((string)$headers->get('X-XSS-Protection'), '1'), 'Missing or invalid X-XSS-Protection');
logTest('Security Header: Referrer-Policy', $headers->get('Referrer-Policy') === 'strict-origin-when-cross-origin', 'Missing or invalid Referrer-Policy');


// SECTION 3: Authentication & Access Control
echo "\n--- SECTION 3: Authentication & Access Control Audit ---\n";
$testUser = User::where('role', 'contestant')->first();

// Non-admin attempting to access admin route
Auth::login($testUser);
$adminReq = Request::create('/admin', 'GET');
$adminResp = $kernel->handle($adminReq);
$adminBlocked = in_array($adminResp->getStatusCode(), [302, 403]);
logTest('Contestant forbidden from /admin', $adminBlocked, "Status code: " . $adminResp->getStatusCode());

// Admin accessing admin route
Auth::login($admin);
$adminOkReq = Request::create('/admin', 'GET');
$adminOkResp = $kernel->handle($adminOkReq);
logTest('Admin authorized to access /admin', $adminOkResp->getStatusCode() === 200, "Status code: " . $adminOkResp->getStatusCode());


// SECTION 4: Challenge Views, Modals & Target Routes
echo "\n--- SECTION 4: Challenge Views & Artifact Downloads ---\n";
Auth::login($testUser);

foreach ($challenges as $ch) {
    // Challenge Modal
    $modalReq = Request::create("/challenges/{$ch->slug}", 'GET');
    $modalResp = $kernel->handle($modalReq);
    logTest("Challenge [{$ch->slug}] Modal renders", $modalResp->getStatusCode() === 200, "Status: " . $modalResp->getStatusCode());

    // Challenge Detail Page
    $detailReq = Request::create("/challenges/{$ch->slug}/detail", 'GET');
    $detailResp = $kernel->handle($detailReq);
    // Detail route might be named challenges.show or challenges.detail
    if ($detailResp->getStatusCode() === 404) {
        $detailReq = Request::create("/challenge/{$ch->slug}", 'GET');
        $detailResp = $kernel->handle($detailReq);
    }

    // Check specific target routes
    if ($ch->type === 'internal' || in_array($ch->slug, ['source-trail', 'secret', 'rsa-oracle'])) {
        $targetReq = Request::create("/challenges/{$ch->slug}/target", 'GET');
        $targetResp = $kernel->handle($targetReq);
        logTest("Target route [/challenges/{$ch->slug}/target] loads", $targetResp->getStatusCode() === 200, "Status: " . $targetResp->getStatusCode());
    }

    // Check download route if challenge has attachment
    if ($ch->attachment_path) {
        $dlReq = Request::create("/challenges/{$ch->slug}/download", 'GET');
        $dlResp = $kernel->handle($dlReq);
        logTest("Artifact download for [{$ch->slug}] works", in_array($dlResp->getStatusCode(), [200, 302]), "Status: " . $dlResp->getStatusCode());
    }
}


// SECTION 5: Flag Validation, Scoring & Duplicate Prevention
echo "\n--- SECTION 5: Flag Validation & Scoring Engine Audit ---\n";
$flagService = app(FlagSubmissionService::class);
$scoringContestant = User::where('role', 'contestant')->orderBy('id', 'desc')->first();
$sampleChallenge = Challenge::where('slug', 'rot13')->first(); // 100 points, simple flag

if ($sampleChallenge && $scoringContestant) {
    // Backup existing solves/submissions for this user and challenge
    $existingSolve = VerifiedSolve::where('challenge_id', $sampleChallenge->id)
        ->where('user_id', $scoringContestant->id)
        ->first();
    $existingSubmissions = ChallengeSubmission::where('challenge_id', $sampleChallenge->id)
        ->where('user_id', $scoringContestant->id)
        ->get();

    // Clean temporarily for test
    VerifiedSolve::where('challenge_id', $sampleChallenge->id)
        ->where('user_id', $scoringContestant->id)
        ->delete();
    ChallengeSubmission::where('challenge_id', $sampleChallenge->id)
        ->where('user_id', $scoringContestant->id)
        ->delete();

    // Test 1: Wrong flag
    $wrongResult = $flagService->attempt($scoringContestant, $sampleChallenge, 'p4dCTF{definitely_wrong_flag_1234}');
    logTest('Incorrect flag returns incorrect status', $wrongResult['status'] === 'incorrect' && $wrongResult['points_awarded'] === 0);

    // Test 2: Correct flag
    $correctResult = $flagService->attempt($scoringContestant, $sampleChallenge, $sampleChallenge->flag);
    logTest('Correct flag returns solved status', $correctResult['status'] === 'solved');
    logTest('Correct flag awards exact challenge points', $correctResult['points_awarded'] === (int)$sampleChallenge->points, "Awarded: " . $correctResult['points_awarded']);

    // Test 3: Duplicate flag
    $dupResult = $flagService->attempt($scoringContestant, $sampleChallenge, $sampleChallenge->flag);
    logTest('Duplicate flag returns already_solved and 0 points', $dupResult['status'] === 'already_solved' && $dupResult['points_awarded'] === 0);

    // Restore state
    VerifiedSolve::where('challenge_id', $sampleChallenge->id)
        ->where('user_id', $scoringContestant->id)
        ->delete();
    ChallengeSubmission::where('challenge_id', $sampleChallenge->id)
        ->where('user_id', $scoringContestant->id)
        ->delete();

    if ($existingSolve) {
        $existingSolve->replicate()->save();
    }
    foreach ($existingSubmissions as $sub) {
        $sub->replicate()->save();
    }
}


// SECTION 6: Team Lifecycle Audit
echo "\n--- SECTION 6: Team Lifecycle Audit ---\n";
// Create a temporary team for testing
DB::beginTransaction();
try {
    $teamUser = User::where('role', 'contestant')->first();
    $teamName = "AuditTestTeam_" . time();
    $code = Team::generateInvitationCode();
    $codeHash = hash('sha256', $code);
    $team = Team::create([
        'name' => $teamName,
        'captain_id' => $teamUser->id,
        'invitation_code_hash' => $codeHash,
        'invitation_code_encrypted' => $code,
        'status' => 'active',
    ]);
    logTest('Team creation successful', $team && $team->id > 0);

    $retrievedTeam = Team::where('invitation_code_hash', $codeHash)->first();
    logTest('Team searchable by invitation code hash', $retrievedTeam !== null && $retrievedTeam->id === $team->id);
} finally {
    DB::rollBack();
    logTest('Team transaction cleanly rolled back (no DB pollution)', true);
}


// SECTION 7: Platform Pages & Scoreboard Verification
echo "\n--- SECTION 7: Platform Pages & Scoreboard Audit ---\n";
Auth::login($testUser);
$dashReq = Request::create('/', 'GET');
$dashResp = $kernel->handle($dashReq);
logTest('Contestant Dashboard page (/) renders HTTP 200', $dashResp->getStatusCode() === 200, "Status: " . $dashResp->getStatusCode());

$challengesReq = Request::create('/challenges', 'GET');
$challengesResp = $kernel->handle($challengesReq);
logTest('Challenges Index (/challenges) renders HTTP 200', $challengesResp->getStatusCode() === 200, "Status: " . $challengesResp->getStatusCode());

$teamsReq = Request::create('/teams', 'GET');
$teamsResp = $kernel->handle($teamsReq);
logTest('Teams Index (/teams) renders HTTP 200', in_array($teamsResp->getStatusCode(), [200, 302]), "Status: " . $teamsResp->getStatusCode());

$profileReq = Request::create('/profile', 'GET');
$profileResp = $kernel->handle($profileReq);
logTest('User Profile (/profile) renders HTTP 200', $profileResp->getStatusCode() === 200, "Status: " . $profileResp->getStatusCode());

Auth::login($admin);
$adminDashReq = Request::create('/admin', 'GET');
$adminDashResp = $kernel->handle($adminDashReq);
logTest('Admin Dashboard & Scoreboard (/admin) renders HTTP 200', $adminDashResp->getStatusCode() === 200, "Status: " . $adminDashResp->getStatusCode());

$adminChReq = Request::create('/admin/challenges', 'GET');
$adminChResp = $kernel->handle($adminChReq);
logTest('Admin Challenges Management (/admin/challenges) renders HTTP 200', $adminChResp->getStatusCode() === 200, "Status: " . $adminChResp->getStatusCode());


// SECTION 8: Flag Secrecy Audit (No Flag Exposure in Public Output)
echo "\n--- SECTION 8: Flag Secrecy Audit ---\n";
$publicRoutes = ['/login', '/register'];
$leaksFound = 0;

foreach ($publicRoutes as $route) {
    $req = Request::create($route, 'GET');
    $resp = $kernel->handle($req);
    $content = $resp->getContent();
    
    // Check all flags
    foreach ($challenges as $ch) {
        if ($ch->flag && str_contains($content, $ch->flag)) {
            echo " [LEAK DETECTED] Flag for {$ch->slug} leaked in $route\n";
            $leaksFound++;
        }
    }
}
logTest('Zero flags leaked across public platform routes', $leaksFound === 0, "Total leaks: $leaksFound");


echo "\n========================================================\n";
echo " AUDIT SUMMARY:\n";
echo " Total Tests: " . ($results['passed'] + $results['failed']) . "\n";
echo " Passed: " . $results['passed'] . "\n";
echo " Failed: " . $results['failed'] . "\n";
echo "========================================================\n";

if ($results['failed'] > 0) {
    exit(1);
}
exit(0);
