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
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');

    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Participant challenge area. Only contestants can browse,
    // view and submit flags.
    Route::middleware('role:contestant')->group(function (): void {
        Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges');
        Route::get('/challenges/{challenge:slug}', [ChallengeController::class, 'show'])->name('challenges.show');
        Route::post('/challenges/{challenge:slug}/submissions', [ChallengeController::class, 'submit'])->name('challenges.submit');
    });

    // The internal challenge target website may be opened by any
    // active authenticated user (contestants and administrators).
    Route::get('/challenges/{challenge:slug}/target', [ChallengeTargetController::class, 'show'])->name('challenges.target');
    Route::post('/challenges/{challenge:slug}/target/verify', [ChallengeTargetController::class, 'verify'])->name('challenges.target.verify');
    Route::get('/challenges/{challenge:slug}/download', [ChallengeController::class, 'download'])->name('challenges.download');

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
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.role');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
        Route::patch('/users/{user}/team', [AdminUserController::class, 'updateTeam'])->name('users.team');
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
        Route::get('/challenges/{challenge:slug}/edit', [AdminChallengeController::class, 'edit'])->name('challenges.edit');
        Route::patch('/challenges/{challenge:slug}', [AdminChallengeController::class, 'update'])->name('challenges.update');
        Route::delete('/challenges/{challenge:slug}', [AdminChallengeController::class, 'destroy'])->name('challenges.destroy');
    });
});
