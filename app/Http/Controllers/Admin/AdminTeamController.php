<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\VerifiedSolve;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminTeamController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', 'unique:teams,name'],
            'captain_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $maxAttempts = 5;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $invitationCode = Team::generateInvitationCode();

                DB::transaction(function () use ($request, $validated, $invitationCode): void {
                    $captain = User::query()->lockForUpdate()->findOrFail($validated['captain_id']);
                    abort_unless($captain->role === 'contestant' && $captain->is_active, 422, 'Choose an active contestant as captain.');
                    abort_if($captain->activeTeams()->exists(), 422, 'The selected captain already belongs to an active team.');

                    $team = Team::create([
                        'name' => $validated['name'],
                        'captain_id' => $captain->id,
                        'invitation_code_hash' => hash('sha256', $invitationCode),
                        'invitation_code_encrypted' => $invitationCode,
                        'status' => 'active',
                    ]);
                    $team->memberships()->create([
                        'user_id' => $captain->id,
                        'role' => 'captain',
                        'joined_at' => now(),
                    ]);
                    AdminAuditLog::create([
                        'actor_user_id' => $request->user()->id,
                        'subject_user_id' => $captain->id,
                        'action' => 'team.created',
                        'details' => ['team_id' => $team->id, 'name' => $team->name, 'captain_id' => $captain->id],
                        'created_at' => now(),
                    ]);
                });

                break;
            } catch (\Illuminate\Database\QueryException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'invitation_code_hash') && $attempt < $maxAttempts - 1) {
                    continue;
                }

                throw $exception;
            }
        }

        return back()->with('status', 'Team created.');
    }

    public function destroyMembership(Request $request, TeamMembership $membership): RedirectResponse
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403, 'Unauthorized administrative action.');

        $membership->loadMissing(['team', 'user']);

        $memberName = $membership->user?->username ?? 'Former account';
        $team = $membership->team;
        $teamName = $team?->name ?? 'Closed team';
        $wasCaptain = $membership->role === 'captain' || ($team && $team->captain_id === $membership->user_id);
        $newCaptainUsername = null;
        $actionTaken = 'deleted';

        DB::transaction(function () use ($request, $membership, $team, $teamName, $wasCaptain, &$newCaptainUsername, &$actionTaken): void {
            if ($wasCaptain && $team !== null && $membership->left_at === null) {
                $nextCaptainMembership = TeamMembership::query()
                    ->where('team_id', $team->id)
                    ->where('user_id', '!=', $membership->user_id)
                    ->whereNull('left_at')
                    ->orderBy('joined_at', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($nextCaptainMembership !== null) {
                    $nextCaptainMembership->update(['role' => 'captain']);
                    $team->update(['captain_id' => $nextCaptainMembership->user_id]);
                    $newCaptainUsername = $nextCaptainMembership->user?->username;

                    AdminAuditLog::create([
                        'actor_user_id' => $request->user()->id,
                        'subject_user_id' => $nextCaptainMembership->user_id,
                        'action' => 'team.captain.reassigned',
                        'details' => [
                            'team_id' => $team->id,
                            'team_name' => $team->name,
                            'previous_captain_id' => $membership->user_id,
                            'new_captain_id' => $nextCaptainMembership->user_id,
                            'reason' => 'captain_removed_by_admin',
                            'timestamp' => now()->toIso8601String(),
                        ],
                        'created_at' => now(),
                    ]);
                } else {
                    $team->update(['captain_id' => null, 'status' => 'inactive']);
                }
            }

            $hasSolves = VerifiedSolve::where('user_id', $membership->user_id)
                ->where('team_id_at_solve', $membership->team_id)
                ->exists();

            if ($hasSolves && $membership->left_at === null) {
                $membership->update(['left_at' => now()]);
                $actionTaken = 'deactivated';
            } else {
                $membership->delete();
                $actionTaken = 'deleted';
            }

            AdminAuditLog::create([
                'actor_user_id' => $request->user()->id,
                'subject_user_id' => $membership->user_id,
                'action' => $actionTaken === 'deactivated' ? 'team.membership.deactivated' : 'team.membership.deleted',
                'details' => [
                    'membership_id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'username' => $membership->user?->username,
                    'team_id' => $membership->team_id,
                    'team_name' => $teamName,
                    'role' => $membership->role,
                    'was_captain' => $wasCaptain,
                    'new_captain' => $newCaptainUsername,
                    'action_taken' => $actionTaken,
                    'timestamp' => now()->toIso8601String(),
                ],
                'created_at' => now(),
            ]);
        });

        $statusMessage = "Member {$memberName} removed from team {$teamName}.";
        if ($newCaptainUsername !== null) {
            $statusMessage .= " Captaincy reassigned to {$newCaptainUsername}.";
        } elseif ($wasCaptain) {
            $statusMessage .= " Team is now empty and marked inactive.";
        }

        return redirect()->route('admin.dashboard')
            ->with('status', $statusMessage);
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403, 'Unauthorized administrative action.');

        $teamName = $team->name;
        $teamId = $team->id;
        $captainId = $team->captain_id;

        try {
            DB::transaction(function () use ($request, $teamId, $teamName, $captainId): void {
                $lockedTeam = Team::query()->lockForUpdate()->findOrFail($teamId);

                // Unbind verified solves from the deleted team to preserve user scores and solve history
                VerifiedSolve::where('team_id_at_solve', $teamId)->update(['team_id_at_solve' => null]);

                // Delete team memberships belonging to this team
                TeamMembership::where('team_id', $teamId)->delete();

                // Clear captain_id foreign key constraint before removing the team
                $lockedTeam->update(['captain_id' => null]);

                // Delete the team record (removes team and its invitation codes)
                $lockedTeam->delete();

                AdminAuditLog::create([
                    'actor_user_id' => $request->user()->id,
                    'subject_user_id' => null,
                    'action' => 'team.deleted',
                    'details' => [
                        'team_id' => $teamId,
                        'name' => $teamName,
                        'captain_id' => $captainId,
                        'deleted_at' => now()->toIso8601String(),
                    ],
                    'created_at' => now(),
                ]);
            });

            return redirect()->route('admin.dashboard')
                ->with('status', "Team '{$teamName}' deleted successfully.");
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.dashboard')
                ->withErrors(['team' => "Failed to delete team '{$teamName}'."]);
        }
    }
}
