<?php

namespace App\Http\Controllers\Contestant;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $team = $request->user()->activeTeams()->with('captain:id,username')->first();

        if ($team !== null) {
            return redirect()->route('teams.show', $team);
        }

        return view('teams.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', 'unique:teams,name'],
        ]);

        $maxAttempts = 5;
        $team = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $invitationCode = $this->newInvitationCode();

                $team = DB::transaction(function () use ($request, $validated, $invitationCode): Team {
                    $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                    if ($user->activeTeams()->exists()) {
                        throw ValidationException::withMessages(['invitation_code' => 'You already belong to an active team.']);
                    }

                    $team = Team::create([
                        'name' => $validated['name'],
                        'captain_id' => $user->id,
                        'invitation_code_hash' => hash('sha256', $invitationCode),
                        'invitation_code_encrypted' => $invitationCode,
                        'status' => 'active',
                    ]);

                    $team->memberships()->create([
                        'user_id' => $user->id,
                        'role' => 'captain',
                        'joined_at' => now(),
                    ]);

                    $this->audit($user, $user, 'team.created', ['team_id' => $team->id]);

                    return $team;
                });

                break;
            } catch (QueryException $exception) {
                if ($this->isInvitationConflict($exception) && $attempt < $maxAttempts - 1) {
                    continue;
                }

                if ($this->isMembershipConflict($exception)) {
                    return back()->withErrors(['name' => 'You already belong to an active team.']);
                }

                throw $exception;
            }
        }

        return redirect()->route('teams.show', $team)->with('status', 'Team created. You are its captain.');
    }

    public function joinForm(): View|RedirectResponse
    {
        if (auth()->user()->activeTeams()->exists()) {
            return redirect()->route('teams.index')->withErrors(['invitation_code' => 'You already belong to an active team.']);
        }

        return view('teams.join');
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invitation_code' => ['required', 'string', 'size:6', 'regex:/\A[0-9]{6}\z/'],
        ], [
            'invitation_code.required' => 'Enter a 6-digit invitation code.',
            'invitation_code.size' => 'The invitation code must be exactly 6 digits.',
            'invitation_code.regex' => 'The invitation code must contain digits only.',
        ]);
        $codeHash = hash('sha256', $validated['invitation_code']);

        try {
            $team = DB::transaction(function () use ($request, $codeHash): Team {
                $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
                if ($user->activeTeams()->exists()) {
                    throw ValidationException::withMessages(['invitation_code' => 'You already belong to an active team.']);
                }

                $team = Team::query()
                    ->where('invitation_code_hash', $codeHash)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if ($team === null) {
                    throw ValidationException::withMessages(['invitation_code' => 'That invitation code is invalid or no longer active.']);
                }

                $team->memberships()->create([
                    'user_id' => $user->id,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);

                $this->audit($user, $user, 'team.member.joined', ['team_id' => $team->id]);

                return $team;
            });
        } catch (QueryException $exception) {
            if ($this->isMembershipConflict($exception)) {
                return back()->withErrors(['invitation_code' => 'You already belong to an active team.']);
            }

            throw $exception;
        }

        return redirect()->route('teams.show', $team)->with('status', 'You joined the team.');
    }

    public function show(Request $request, Team $team): View
    {
        abort_unless($team->status === 'active', 404);
        abort_unless($team->activeMembers()->whereKey($request->user()->id)->exists(), 404);

        $team->load('captain:id,username');
        $memberships = $team->memberships()
            ->with('user:id,username')
            ->orderByRaw('left_at IS NULL DESC')
            ->orderBy('joined_at')
            ->get();
        $pointsByMember = [];

        foreach ($memberships as $membership) {
            $solves = $membership->user
                ->verifiedSolves()
                ->where('team_id_at_solve', $team->id)
                ->where('verified_at', '>=', $membership->joined_at);

            if ($membership->left_at !== null) {
                $solves->where('verified_at', '<', $membership->left_at);
            }

            $pointsByMember[$membership->id] = $solves->sum('points_awarded');
        }

        return view('teams.show', [
            'team' => $team,
            'memberships' => $memberships,
            'teamPoints' => $team->attributedSolves()->sum('points_awarded'),
            'personalPoints' => $request->user()->verifiedSolves()->sum('points_awarded'),
            'pointsByMember' => $pointsByMember,
            'isCaptain' => $team->captain_id === $request->user()->id,
        ]);
    }

    public function rename(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCaptain($request, $team);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', 'unique:teams,name,'.$team->id],
        ]);
        $previousName = $team->name;
        $team->update(['name' => $validated['name']]);
        $this->audit($request->user(), $request->user(), 'team.renamed', [
            'team_id' => $team->id,
            'from' => $previousName,
            'to' => $team->name,
        ]);

        return back()->with('status', 'Team name updated.');
    }

    public function regenerateInvitation(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCaptain($request, $team);

        $maxAttempts = 5;
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $invitationCode = $this->newInvitationCode();

                DB::transaction(function () use ($request, $team, $invitationCode): void {
                    $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
                    abort_unless($lockedTeam->status === 'active', 404);
                    $lockedTeam->update([
                        'invitation_code_hash' => hash('sha256', $invitationCode),
                        'invitation_code_encrypted' => $invitationCode,
                    ]);
                    $this->audit($request->user(), $request->user(), 'team.invitation.regenerated', ['team_id' => $team->id]);
                });

                break;
            } catch (QueryException $exception) {
                if ($this->isInvitationConflict($exception) && $attempt < $maxAttempts - 1) {
                    continue;
                }

                throw $exception;
            }
        }

        return back()->with('status', 'The invitation code was regenerated. Previous codes no longer work.');
    }

    public function removeMember(Request $request, Team $team, User $user): RedirectResponse
    {
        $this->authorizeCaptain($request, $team);
        abort_if($user->id === $team->captain_id, 422, 'Transfer captaincy before leaving the team.');

        DB::transaction(function () use ($request, $team, $user): void {
            Team::query()->lockForUpdate()->findOrFail($team->id);
            $membership = TeamMembership::query()
                ->where('team_id', $team->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();
            $membership->update(['left_at' => now()]);
            $this->audit($request->user(), $user, 'team.member.removed', ['team_id' => $team->id]);
        });

        return back()->with('status', 'Team member removed. Membership history and solve attribution are preserved.');
    }

    public function transferCaptain(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCaptain($request, $team);
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        abort_if((int) $validated['user_id'] === $request->user()->id, 422, 'You are already the captain.');

        DB::transaction(function () use ($request, $team, $validated): void {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
            $nextCaptain = TeamMembership::query()
                ->where('team_id', $team->id)
                ->where('user_id', $validated['user_id'])
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();
            $currentCaptain = TeamMembership::query()
                ->where('team_id', $team->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();

            $currentCaptain->update(['role' => 'member']);
            $nextCaptain->update(['role' => 'captain']);
            $lockedTeam->update(['captain_id' => $nextCaptain->user_id]);
            $this->audit($request->user(), $nextCaptain->user, 'team.captain.transferred', ['team_id' => $team->id]);
        });

        return back()->with('status', 'Captaincy transferred.');
    }

    public function leave(Request $request, Team $team): RedirectResponse
    {
        DB::transaction(function () use ($request, $team): void {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
            abort_unless($lockedTeam->activeMembers()->whereKey($request->user()->id)->exists(), 403);
            abort_if($lockedTeam->captain_id === $request->user()->id, 422, 'Transfer captaincy before leaving the team.');

            $membership = TeamMembership::query()
                ->where('team_id', $team->id)
                ->where('user_id', $request->user()->id)
                ->whereNull('left_at')
                ->lockForUpdate()
                ->firstOrFail();
            $membership->update(['left_at' => now()]);
            $this->audit($request->user(), $request->user(), 'team.member.left', ['team_id' => $team->id]);
        });

        return redirect()->route('teams.index')->with('status', 'You left the team. Historical scores remain attributed to it.');
    }

    private function authorizeCaptain(Request $request, Team $team): void
    {
        abort_unless($team->status === 'active' && $team->captain_id === $request->user()->id, 403);
    }

    private function newInvitationCode(): string
    {
        return Team::generateInvitationCode();
    }

    private function isInvitationConflict(QueryException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'invitation_code_hash');
    }

    private function audit(User $actor, User $subject, string $action, array $details): void
    {
        AdminAuditLog::create([
            'actor_user_id' => $actor->id,
            'subject_user_id' => $subject->id,
            'action' => $action,
            'details' => $details,
            'created_at' => now(),
        ]);
    }

    private function isMembershipConflict(QueryException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'team_memberships_one_active_user')
            || str_contains(strtolower($exception->getMessage()), 'team_memberships.user_id');
    }
}
