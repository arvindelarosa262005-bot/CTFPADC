<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\VerifiedSolve;
use App\Models\AdminAuditLog;

echo "--- USERS ---\n";
foreach (User::all() as $u) {
    echo "ID: {$u->id} | {$u->username} | {$u->email} | {$u->role} | active: " . ($u->is_active ? '1' : '0') . "\n";
}

echo "\n--- TEAMS ---\n";
foreach (Team::all() as $t) {
    echo "ID: {$t->id} | {$t->name} | captain_id: {$t->captain_id} | status: {$t->status}\n";
}

echo "\n--- TEAM MEMBERSHIPS ---\n";
foreach (TeamMembership::with(['team', 'user'])->get() as $m) {
    echo "ID: {$m->id} | user: " . ($m->user?->username ?? 'null (' . $m->user_id . ')') . " | team: " . ($m->team?->name ?? 'null (' . $m->team_id . ')') . " | role: {$m->role} | left_at: " . ($m->left_at ?? 'active') . "\n";
}

echo "\n--- VERIFIED SOLVES ---\n";
foreach (VerifiedSolve::with(['challenge', 'user'])->get() as $s) {
    echo "ID: {$s->id} | user: " . ($s->user?->username ?? $s->user_id) . " | chal: " . ($s->challenge?->title ?? $s->challenge_id) . " | pts: {$s->points_awarded}\n";
}

echo "\n--- LATEST 5 AUDIT LOGS ---\n";
foreach (AdminAuditLog::latest('id')->limit(5)->get() as $l) {
    echo "ID: {$l->id} | {$l->action} | actor: {$l->actor_user_id} | details: " . json_encode($l->details) . "\n";
}

