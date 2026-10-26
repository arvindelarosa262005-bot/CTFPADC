<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot change your own role.');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'contestant'])],
        ]);

        abort_if(($user->isPrimaryAdmin() || strtolower($user->username) === 'admin') && $validated['role'] !== 'admin', 403, 'Use the authorized recovery command to change the primary administrator.');

        $previousRole = $user->role;
        abort_if(
            $previousRole === 'admin' && $validated['role'] !== 'admin' && $user->is_active && User::where('role', 'admin')->where('is_active', true)->count() < 2,
            422,
            'At least one active administrator must remain.'
        );

        abort_if(
            $validated['role'] !== 'contestant' && Team::where('captain_id', $user->id)->where('status', 'active')->exists(),
            422,
            'Active team captains must transfer captaincy before their role can be changed.'
        );

        DB::transaction(function () use ($request, $user, $validated, $previousRole): void {
            if ($previousRole === 'contestant' && $validated['role'] !== 'contestant') {
                foreach (Team::where('captain_id', $user->id)->where('status', 'active')->get() as $team) {
                    $nextCaptain = TeamMembership::query()
                        ->where('team_id', $team->id)
                        ->where('user_id', '!=', $user->id)
                        ->whereNull('left_at')
                        ->orderBy('joined_at', 'asc')
                        ->lockForUpdate()
                        ->first();

                    if ($nextCaptain !== null) {
                        $nextCaptain->update(['role' => 'captain']);
                        $team->update(['captain_id' => $nextCaptain->user_id]);
                    } else {
                        $team->update(['captain_id' => null, 'status' => 'inactive']);
                    }
                }
            }

            $user->update(['role' => $validated['role']]);
            $this->audit($request, $user, 'user.role.updated', [
                'from' => $previousRole,
                'to' => $validated['role'],
            ]);
        });

        return back()->with('status', 'User role updated.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $isActive = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);

        abort_if(($user->isPrimaryAdmin() || strtolower($user->username) === 'admin') && ! $isActive, 403, 'Use the authorized recovery command to deactivate the primary administrator.');

        abort_if($request->user()->is($user) && ! $isActive, 403, 'You cannot deactivate your own account.');
        abort_if(
            $user->role === 'admin' && $user->is_active && ! $isActive && User::where('role', 'admin')->where('is_active', true)->count() < 2,
            422,
            'At least one active administrator must remain.'
        );

        abort_if(
            ! $isActive && Team::where('captain_id', $user->id)->where('status', 'active')->exists(),
            422,
            'Active team captains must transfer captaincy before their account can be deactivated.'
        );

        $previousStatus = $user->is_active;

        DB::transaction(function () use ($request, $user, $isActive, $previousStatus): void {
            if ($user->is_active && ! $isActive) {
                foreach (Team::where('captain_id', $user->id)->where('status', 'active')->get() as $team) {
                    $nextCaptain = TeamMembership::query()
                        ->where('team_id', $team->id)
                        ->where('user_id', '!=', $user->id)
                        ->whereNull('left_at')
                        ->orderBy('joined_at', 'asc')
                        ->lockForUpdate()
                        ->first();

                    if ($nextCaptain !== null) {
                        $nextCaptain->update(['role' => 'captain']);
                        $team->update(['captain_id' => $nextCaptain->user_id]);
                    } else {
                        $team->update(['captain_id' => null, 'status' => 'inactive']);
                    }
                }
            }

            $user->update(['is_active' => $isActive]);
            $this->audit($request, $user, 'user.status.updated', [
                'from' => $previousStatus,
                'to' => $isActive,
            ]);
        });

        return back()->with('status', 'User account status updated.');
    }

    public function updateTeam(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);
        $teamId = $validated['team_id'] ?? null;

        DB::transaction(function () use ($request, $user, $teamId): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $currentMembership = TeamMembership::query()
                ->where('user_id', $lockedUser->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->first();

            $targetTeam = $teamId === null ? null : Team::query()->lockForUpdate()->findOrFail($teamId);
            abort_if($targetTeam !== null && $targetTeam->status !== 'active', 422, 'That team is not accepting members.');
            abort_if($targetTeam !== null && $currentMembership?->team_id === $targetTeam->id, 422, 'This account is already on that team.');

            $previousTeamId = $currentMembership?->team_id;

            if ($currentMembership !== null) {
                if ($currentMembership->role === 'captain' && $previousTeamId !== null) {
                    $previousTeam = Team::query()->lockForUpdate()->find($previousTeamId);
                    if ($previousTeam !== null) {
                        $nextCaptain = TeamMembership::query()
                            ->where('team_id', $previousTeam->id)
                            ->where('user_id', '!=', $lockedUser->id)
                            ->whereNull('left_at')
                            ->orderBy('joined_at', 'asc')
                            ->lockForUpdate()
                            ->first();

                        if ($nextCaptain !== null) {
                            $nextCaptain->update(['role' => 'captain']);
                            $previousTeam->update(['captain_id' => $nextCaptain->user_id]);
                            $this->audit($request, $nextCaptain->user, 'team.captain.reassigned', [
                                'team_id' => $previousTeam->id,
                                'team_name' => $previousTeam->name,
                                'previous_captain_id' => $lockedUser->id,
                                'new_captain_id' => $nextCaptain->user_id,
                                'reason' => 'captain_removed_by_admin',
                            ]);
                        } else {
                            $previousTeam->update(['captain_id' => null, 'status' => 'inactive']);
                        }
                    }
                }

                $currentMembership->update(['left_at' => now()]);
            }

            if ($targetTeam !== null) {
                $targetTeam->memberships()->create([
                    'user_id' => $lockedUser->id,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }

            $this->audit($request, $user, 'user.team_membership.updated', [
                'from_team_id' => $previousTeamId,
                'to_team_id' => $teamId,
            ]);
        });

        return back()->with('status', 'Team membership updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if(
            $user->isPrimaryAdmin() || strtolower($user->username) === 'admin',
            403,
            'The primary administrator cannot be deleted.'
        );

        abort_if(
            $request->user()->is($user),
            403,
            'You cannot delete your own account.'
        );

        abort_if(
            $user->isAdmin(),
            403,
            'Protected administrator accounts cannot be deleted.'
        );

        $activeTeamsWhereCaptain = Team::where('captain_id', $user->id)
            ->where('status', 'active')
            ->get();

        $deletedUsername = $user->username;

        DB::transaction(function () use ($request, $user, $activeTeamsWhereCaptain): void {
            $this->audit($request, $user, 'user.deleted', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'deleted_at' => now()->toIso8601String(),
            ]);

            foreach ($activeTeamsWhereCaptain as $team) {
                $nextCaptain = TeamMembership::query()
                    ->where('team_id', $team->id)
                    ->where('user_id', '!=', $user->id)
                    ->whereNull('left_at')
                    ->orderBy('joined_at', 'asc')
                    ->lockForUpdate()
                    ->first();

                if ($nextCaptain !== null) {
                    $nextCaptain->update(['role' => 'captain']);
                    $team->update(['captain_id' => $nextCaptain->user_id]);
                    $this->audit($request, $nextCaptain->user, 'team.captain.reassigned', [
                        'team_id' => $team->id,
                        'team_name' => $team->name,
                        'previous_captain_id' => $user->id,
                        'new_captain_id' => $nextCaptain->user_id,
                        'reason' => 'captain_account_deleted',
                    ]);
                } else {
                    $team->update(['captain_id' => null, 'status' => 'inactive']);
                }
            }

            Team::where('captain_id', $user->id)
                ->where('status', '!=', 'active')
                ->update(['captain_id' => null]);

            $user->teamMemberships()->delete();
            $user->delete();
        });

        return redirect()->route('admin.dashboard')
            ->with('status', "User account {$deletedUsername} deleted.");
    }

    public function destroySolves(Request $request, User $user): RedirectResponse
    {
        abort_if(
            $user->isPrimaryAdmin() || strtolower($user->username) === 'admin',
            403,
            'The primary administrator records are protected.'
        );

        $solvesCount = $user->verifiedSolves()->count();
        $pointsSum = (int) $user->verifiedSolves()->sum('points_awarded');

        DB::transaction(function () use ($request, $user, $solvesCount, $pointsSum): void {
            $user->verifiedSolves()->delete();

            $this->audit($request, $user, 'user.solves.deleted', [
                'user_id' => $user->id,
                'username' => $user->username,
                'solves_removed' => $solvesCount,
                'points_removed' => $pointsSum,
                'timestamp' => now()->toIso8601String(),
            ]);
        });

        return redirect()->route('admin.dashboard')
            ->with('status', "Verified solves ({$solvesCount} solves, {$pointsSum} points) for user {$user->username} deleted.");
    }

    private function audit(Request $request, User $subject, string $action, array $details): void
    {
        AdminAuditLog::create([
            'actor_user_id' => $request->user()->id,
            'subject_user_id' => $subject->id,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}
