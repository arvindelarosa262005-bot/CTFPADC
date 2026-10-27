<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\ChallengeTargetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminTeamController;
use App\Http\Controllers\Admin\AdminChallengeController;
use App\Http\Controllers\Admin\AdminScoreController;
use App\Http\Controllers\Contestant\TeamController;
use App\Http\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

// Public PADC BSIT Landing Page & Showcase
Route::get('/', [LandingPageController::class, 'index'])->name('home');
Route::get('/padc', [LandingPageController::class, 'index'])->name('landing');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');

    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Challenge arena. Active contestants and administrators can browse,
    // view, and submit flags. Mapped primarily to /arena (with /challenges alias)
    // so participant browsers never get blocked by stale container-port redirects.
    Route::get('/arena', [ChallengeController::class, 'index'])->name('challenges');
    Route::get('/arena/{challenge:slug}', [ChallengeController::class, 'show'])->name('challenges.show');
    Route::post('/arena/{challenge:slug}/submissions', [ChallengeController::class, 'submit'])->name('challenges.submit');
    Route::get('/arena/{challenge:slug}/target', [ChallengeTargetController::class, 'show'])->name('challenges.target');
    Route::post('/arena/{challenge:slug}/target/verify', [ChallengeTargetController::class, 'verify'])->name('challenges.target.verify');
    Route::get('/arena/{challenge:slug}/download', [ChallengeController::class, 'download'])->name('challenges.download');

    // Dual-alias fallback routes for /challenges:
    Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.legacy');
    Route::get('/challenges/{challenge:slug}', [ChallengeController::class, 'show']);
    Route::post('/challenges/{challenge:slug}/submissions', [ChallengeController::class, 'submit']);
    Route::get('/challenges/{challenge:slug}/target', [ChallengeTargetController::class, 'show']);
    Route::post('/challenges/{challenge:slug}/target/verify', [ChallengeTargetController::class, 'verify']);
    Route::get('/challenges/{challenge:slug}/download', [ChallengeController::class, 'download']);

    Route::middleware('role:contestant')->prefix('teams')->name('teams.')->group(function (): void {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/', [TeamController::class, 'store'])->middleware('throttle:5,1')->name('store');
        Route::get('/join', [TeamController::class, 'joinForm'])->name('join');
        Route::post('/join', [TeamController::class, 'join'])->middleware('throttle:5,1')->name('join.store');
        Route::get('/{team}', [TeamController::class, 'show'])->whereNumber('team')->name('show');
        Route::patch('/{team}/name', [TeamController::class, 'rename'])->whereNumber('team')->name('rename');
        Route::post('/{team}/invitation/regenerate', [TeamController::class, 'regenerateInvitation'])->whereNumber('team')->middleware('throttle:3,1')->name('invitation.regenerate');
        Route::delete('/{team}/members/{user}', [TeamController::class, 'removeMember'])->whereNumber('team')->name('members.remove');
        Route::post('/{team}/captain', [TeamController::class, 'transferCaptain'])->whereNumber('team')->name('captain.transfer');
        Route::post('/{team}/leave', [TeamController::class, 'leave'])->whereNumber('team')->name('leave');
    });

    Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');
    Route::patch('/profile', [DashboardController::class, 'updateProfile'])->name('profile.update');
    Route::match(['get', 'post'], '/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/scores/live', [AdminDashboardController::class, 'liveScores'])->name('scores.live');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::patch('/users/{user}/team', [AdminUserController::class, 'updateTeam'])->name('users.team');
        Route::post('/users/{user}/ai-flag/clear', [AdminUserController::class, 'clearAiFlag'])->name('users.ai_clear');
        Route::post('/users/{user}/ai-flag/disqualify', [AdminUserController::class, 'disqualifyForAi'])->name('users.ai_disqualify');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::delete('/users/{user}/solves', [AdminUserController::class, 'destroySolves'])->name('users.solves.destroy');
        Route::delete('/memberships/{membership}', [AdminTeamController::class, 'destroyMembership'])->name('memberships.destroy');
        Route::post('/teams', [AdminTeamController::class, 'store'])->name('teams.store');
        Route::delete('/teams/{team}', [AdminTeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('/scores/recalculate', [AdminScoreController::class, 'recalculate'])->name('scores.recalculate');
        Route::post('/scores/reset', [AdminScoreController::class, 'reset'])->name('scores.reset');

        // Challenge management. Only administrators.
        Route::get('/challenges', [AdminChallengeController::class, 'index'])->name('challenges.index');
        Route::get('/challenges/create', [AdminChallengeController::class, 'create'])->name('challenges.create');
        Route::post('/challenges', [AdminChallengeController::class, 'store'])->name('challenges.store');
        Route::post('/challenges/bulk-release', [AdminChallengeController::class, 'bulkRelease'])->name('challenges.bulk_release');
        Route::post('/challenges/bulk-hide', [AdminChallengeController::class, 'bulkHide'])->name('challenges.bulk_hide');
        Route::post('/challenges/{challenge:slug}/toggle', [AdminChallengeController::class, 'toggle'])->name('challenges.toggle');
        Route::get('/challenges/{challenge:slug}/edit', [AdminChallengeController::class, 'edit'])->name('challenges.edit');
        Route::patch('/challenges/{challenge:slug}', [AdminChallengeController::class, 'update'])->name('challenges.update');
        Route::delete('/challenges/{challenge:slug}', [AdminChallengeController::class, 'destroy'])->name('challenges.destroy');
    });
});
