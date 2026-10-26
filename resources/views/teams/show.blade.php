@extends('layouts.app')

@section('title', $team->name)
@section('section', 'TEAM PROFILE')

@section('content')
    @if (session('status'))<p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>@endif
    <section class="welcome-row team-welcome">
        <div><div class="eyebrow">TEAM PROFILE <span class="eyebrow-divider">/</span> {{ $team->status === 'active' ? 'ACTIVE' : 'UNAVAILABLE' }}</div><h1>{{ $team->name }}</h1></div>
        <span class="team-captain-tag">CAPTAIN · {{ $team->captain?->username }}</span>
    </section>

    <section class="team-score-summary" aria-label="Verified score summary">
        <article><span>TEAM VERIFIED POINTS</span><strong>{{ number_format($teamPoints) }}</strong><small>Sum of member solves attributed at verification</small></article>
        <article><span>YOUR VERIFIED POINTS</span><strong>{{ number_format($personalPoints) }}</strong><small>Your individual verified solve history</small></article>
        <article><span>ACTIVE MEMBERS</span><strong>{{ $memberships->whereNull('left_at')->count() }} / 4</strong><small>{{ $memberships->whereNull('left_at')->count() >= 4 ? 'Squad is full (max 4)' : 'Max 4 operatives per team' }}</small></article>
    </section>

    @if ($isCaptain)
        <section class="team-panel captain-panel">
            <div class="section-heading"><div><div class="eyebrow">CAPTAIN CONTROLS <span class="eyebrow-divider">/</span> INVITATION</div><h2>Team management</h2></div></div>
            <form class="team-name-form" method="POST" action="{{ route('teams.rename', $team) }}">@csrf @method('PATCH')<label for="rename-team">Team name</label><input id="rename-team" name="name" value="{{ $team->name }}" maxlength="60" required><button class="small-action" type="submit">Update name</button>@error('name')<span class="field-error">{{ $message }}</span>@enderror</form>
            <div class="invitation-box">
                <label for="team-invitation-code">Invitation code</label>
                @if ($memberships->whereNull('left_at')->count() >= 4)
                    <p class="invitation-warning" style="color: var(--amber); font-weight: 600;">⚠ Team roster is at maximum capacity (4/4 members). New members cannot join until a slot opens.</p>
                @elseif ($team->invitation_code_encrypted)
                    <div class="invitation-code-row"><input id="team-invitation-code" type="text" value="{{ $team->invitation_code_encrypted }}" readonly aria-label="Team invitation code"><button class="small-action copy-code" type="button" data-copy-target="team-invitation-code">Copy code</button></div>
                    <p class="invitation-warning">Share privately. Maximum 4 operatives per squad. Regenerating it immediately revokes the previous code.</p>
                @else
                    <p class="invitation-warning">This team has no active invitation code.</p>
                @endif
                <form method="POST" action="{{ route('teams.invitation.regenerate', $team) }}" onsubmit="return confirm('Regenerate the invitation code? The previous code will stop working.');">@csrf<button class="small-action" type="submit">{{ $team->invitation_code_encrypted ? 'Regenerate code' : 'Create invitation code' }}</button></form>
            </div>
        </section>
    @endif

    <section class="admin-section team-members-section">
        <div class="section-heading"><div><div class="eyebrow">ROSTER <span class="eyebrow-divider">/</span> SQUAD (MAX 4)</div><h2>Team members</h2></div><span class="admin-section-note">SOLVES STAY WITH THE TEAM RECORDED AT VERIFICATION</span></div>
        <div class="table-scroll"><table class="admin-table team-member-table"><thead><tr><th>OPERATIVE / CALLSIGN</th><th>MEMBERSHIP</th><th>JOINED</th><th>LEFT</th><th>POINTS CONTRIBUTED</th>@if ($isCaptain)<th>MANAGE</th>@endif</tr></thead><tbody>
            @forelse ($memberships as $membership)
                <tr>
                    <td>
                        <strong>{{ $membership->user?->nickname ?: $membership->user?->username ?? 'Former account' }}</strong>
                        @if ($membership->user?->nickname)
                            <small style="color: var(--muted);">{{ '@' . $membership->user->username }}</small>
                        @endif
                        @if ($membership->role === 'captain')
                            <span class="captain-badge">CAPTAIN</span>
                        @endif
                    </td>
                    <td>{{ $membership->left_at ? 'Former member' : 'Active' }}</td>
                    <td>{{ $membership->joined_at?->format('Y-m-d') }}</td>
                    <td>{{ $membership->left_at?->format('Y-m-d') ?? '—' }}</td>
                    <td>{{ number_format($pointsByMember[$membership->id] ?? 0) }}</td>
                    @if ($isCaptain)<td>@if (! $membership->left_at && $membership->user_id !== auth()->id())<div class="member-actions"><form method="POST" action="{{ route('teams.members.remove', [$team, $membership->user_id]) }}" onsubmit="return confirm('Remove this member? Their historical membership and solve attribution will remain.');">@csrf @method('DELETE')<button class="small-action" type="submit">Remove</button></form><form method="POST" action="{{ route('teams.captain.transfer', $team) }}">@csrf<input type="hidden" name="user_id" value="{{ $membership->user_id }}"><button class="small-action" type="submit" onclick="return confirm('Transfer captaincy to {{ $membership->user?->username }}?');">Make captain</button></form></div>@else<span class="state-label">—</span>@endif</td>@endif
                </tr>
            @empty
                <tr><td colspan="{{ $isCaptain ? 6 : 5 }}">No membership records.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    @unless ($isCaptain)
        <form class="leave-team-form" method="POST" action="{{ route('teams.leave', $team) }}" onsubmit="return confirm('Leave this team? Your historical points will remain attributed to it.');">@csrf<button class="small-action" type="submit">Leave team</button></form>
    @endunless
@endsection
