<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\Challenge;
use App\Models\VerifiedSolve;
use App\Models\ChallengeSubmission;

echo "========================================================\n";
echo "P4DC CTF DATABASE BASELINE INVENTORY\n";
echo "========================================================\n\n";

echo "USERS (" . User::count() . "):\n";
foreach (User::orderBy('id')->get(['id', 'username', 'email', 'role', 'is_active']) as $u) {
    echo sprintf("  [%2d] %-15s | %-25s | Role: %-10s | Active: %d\n", $u->id, $u->username, $u->email, $u->role, $u->is_active ? 1 : 0);
}

echo "\nTEAMS (" . Team::count() . "):\n";
foreach (Team::orderBy('id')->get() as $t) {
    echo sprintf("  [%2d] %-20s | Code: %-8s | Captain ID: %s\n", $t->id, $t->name, $t->invitation_code, $t->captain_id ?? 'None');
}

echo "\nTEAM MEMBERSHIPS (" . TeamMembership::count() . "):\n";
foreach (TeamMembership::orderBy('id')->get() as $m) {
    echo sprintf("  [%2d] Team #%d -> User #%d | Role: %-8s | Active: %d\n", $m->id, $m->team_id, $m->user_id, $m->role, $m->is_active ? 1 : 0);
}

echo "\nCHALLENGES (" . Challenge::count() . "):\n";
foreach (Challenge::orderBy('id')->get(['id', 'slug', 'title', 'category', 'difficulty', 'points', 'is_active', 'attachment_filename']) as $c) {
    echo sprintf("  [%2d] %-26s | %-26s | %-15s | %-8s | %3d pts | Active: %d | Att: %s\n",
        $c->id, $c->slug, $c->title, $c->category, $c->difficulty, $c->points, $c->is_active ? 1 : 0, $c->attachment_filename ?? 'None');
}

echo "\nVERIFIED SOLVES (" . VerifiedSolve::count() . "):\n";
foreach (VerifiedSolve::orderBy('id')->get() as $s) {
    echo sprintf("  [%2d] User #%d | Challenge #%d | Team #%s | %d pts\n", $s->id, $s->user_id, $s->challenge_id, $s->team_id_at_solve ?? 'None', $s->points_awarded);
}

echo "\nCHALLENGE SUBMISSIONS (" . ChallengeSubmission::count() . ")\n";
echo "========================================================\n";

