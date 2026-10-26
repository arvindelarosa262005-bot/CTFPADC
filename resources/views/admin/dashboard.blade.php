@extends('layouts.app')

@section('title', 'Admin dashboard')
@section('section', 'ADMIN CONSOLE')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="form-notice form-notice-error dashboard-notice" role="alert">{{ session('error') }}</p>
    @endif
    @if (isset($errors) && $errors->any())
        <div class="form-notice form-notice-error dashboard-notice" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <section class="welcome-row admin-welcome">
        <div>
            <div class="eyebrow">RESTRICTED ACCESS <span class="eyebrow-divider">/</span> ADMINISTRATION</div>
            <h1>Control room<span class="admin-heading-dot">.</span></h1>
        </div>
        <div class="date-stamp"><span class="status-dot"></span> ADMIN SESSION</div>
    </section>

    <section class="admin-overview" aria-label="Administrative overview">
        <article class="admin-stat"><span>REGISTERED USERS</span><strong>{{ number_format($registeredUsers) }}</strong><small>Accounts in this range</small></article>
        <article class="admin-stat"><span>EXISTING TEAMS</span><strong>{{ number_format($existingTeams) }}</strong><small>Created by administrators</small></article>
        <article class="admin-stat points-card"><span>POINTS EARNED</span><strong>000</strong><small>Ready to be earned</small><i>PLACEHOLDER · VERIFIED SCORING NOT ACTIVE</i></article>
    </section>

    <section class="admin-section" id="individual-rankings">
        <div class="section-heading"><div><div class="eyebrow">VERIFIED SOLVES <span class="eyebrow-divider">/</span> 01</div><h2>Individual Top Scorers</h2></div><span class="admin-section-note">POINTS DESC · USERNAME ASC</span></div>
        @if ($individualScorers->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">#</span><div><h3>No scores available yet</h3><p>Individual rankings will appear after verified solves are recorded.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table"><thead><tr><th>RANK</th><th>USERNAME</th><th>VERIFIED POINTS</th><th>CHALLENGES SOLVED</th><th>ACTIONS</th></tr></thead><tbody>
                @foreach ($individualScorers as $index => $scorer)
                    <tr>
                        <td class="rank-cell">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $scorer->username }}</td>
                        <td>{{ number_format($scorer->total_verified_points ?? 0) }}</td>
                        <td>{{ number_format($scorer->challenges_solved) }}</td>
                        <td>
                            @if ($scorer->isPrimaryAdmin() || strtolower($scorer->username) === 'admin')
                                <span class="state-label">Protected admin</span>
                            @else
                                <form class="management-form" method="POST" action="{{ route('admin.users.solves.destroy', $scorer) }}" onsubmit="return confirm('Are you sure you want to delete verified solves for user \'{{ $scorer->username }}\'? This will remove {{ $scorer->challenges_solved }} solve(s) and {{ number_format($scorer->total_verified_points ?? 0) }} points. This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="small-action small-action-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><div class="eyebrow">TEAM SOLVES <span class="eyebrow-divider">/</span> 02</div><h2>Team Scorers</h2></div><span class="admin-section-note">POINTS DESC · TEAM NAME ASC</span></div>
        @if ($teamScorers->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">#</span><div><h3>No scores available yet</h3><p>Team rankings will appear after verified team solves are recorded.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table"><thead><tr><th>TEAM RANK</th><th>TEAM NAME</th><th>VERIFIED TEAM POINTS</th><th>MEMBERS</th><th>CHALLENGES SOLVED</th></tr></thead><tbody>
                @foreach ($teamScorers as $index => $team)
                    <tr><td class="rank-cell">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td><td>{{ $team->name }}</td><td>{{ number_format($team->total_verified_points ?? 0) }}</td><td>{{ number_format($team->members_count) }}</td><td>{{ number_format($team->challenges_solved) }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="admin-section" id="account-management">
        <div class="section-heading"><div><div class="eyebrow">IDENTITIES <span class="eyebrow-divider">/</span> 03</div><h2>User accounts &amp; memberships</h2></div><span class="admin-section-note">{{ $users->count() }} ACCOUNTS</span></div>
        @if ($users->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">∅</span><div><h3>No user accounts yet</h3><p>Registered contestants will appear here.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table management-table"><thead><tr><th>ACCOUNT</th><th>ROLE</th><th>STATUS</th><th>TEAM MEMBERSHIP</th><th>ACTIONS</th></tr></thead><tbody>
                @foreach ($users as $managedUser)
                    <tr>
                        <td><strong>{{ $managedUser->username }}</strong><small>{{ $managedUser->email }}</small></td>
                        <td>
                            @if (auth()->user()->is($managedUser))
                                <span class="state-label">{{ ucfirst($managedUser->role) }} · current admin</span>
                            @elseif ($managedUser->isPrimaryAdmin() || strtolower($managedUser->username) === 'admin')
                                <span class="state-label">{{ ucfirst($managedUser->role) }} · primary admin</span>
                            @else
                                <form class="management-form" method="POST" action="{{ route('admin.users.role', $managedUser) }}">@csrf @method('PATCH')<select name="role" aria-label="Role for {{ $managedUser->username }}"><option value="contestant" @selected($managedUser->role === 'contestant')>Contestant</option><option value="admin" @selected($managedUser->role === 'admin')>Admin</option></select><button type="submit" class="small-action">Save</button></form>
                            @endif
                        </td>
                        <td>
                            @if (auth()->user()->is($managedUser))
                                <span class="state-label state-active">{{ $managedUser->is_active ? 'Active' : 'Inactive' }} · current admin</span>
                            @elseif ($managedUser->isPrimaryAdmin() || strtolower($managedUser->username) === 'admin')
                                <span class="state-label state-active">{{ $managedUser->is_active ? 'Active' : 'Inactive' }} · primary admin</span>
                            @else
                                <form class="management-form" method="POST" action="{{ route('admin.users.status', $managedUser) }}">@csrf @method('PATCH')<select name="is_active" aria-label="Account status for {{ $managedUser->username }}"><option value="1" @selected($managedUser->is_active)>Active</option><option value="0" @selected(! $managedUser->is_active)>Inactive</option></select><button type="submit" class="small-action">Save</button></form>
                            @endif
                        </td>
                        <td><form class="management-form" method="POST" action="{{ route('admin.users.team', $managedUser) }}">@csrf @method('PATCH')<select name="team_id" aria-label="Team for {{ $managedUser->username }}"><option value="">No team</option>@foreach ($teams as $team)<option value="{{ $team->id }}" @selected($managedUser->activeTeams->contains('id', $team->id))>{{ $team->name }}</option>@endforeach</select><button type="submit" class="small-action">Save</button></form></td>
                        <td>
                            @if ($managedUser->isPrimaryAdmin() || strtolower($managedUser->username) === 'admin')
                                <span class="state-label">Protected admin</span>
                            @elseif (auth()->user()->is($managedUser))
                                <span class="state-label">Current admin</span>
                            @elseif ($managedUser->isAdmin())
                                <span class="state-label">Admin account</span>
                            @else
                                <form class="management-form" method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" onsubmit="return confirm('Are you sure you want to delete user \'{{ $managedUser->username }}\'? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="small-action small-action-danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><div class="eyebrow">ROSTER <span class="eyebrow-divider">/</span> 04</div><h2>Teams</h2></div></div>
        <form class="team-create-form" method="POST" action="{{ route('admin.teams.store') }}">@csrf<label for="team-name">Create team</label><input id="team-name" name="name" maxlength="60" required placeholder="Team name"><label for="team-captain">Captain</label><select id="team-captain" name="captain_id" required><option value="">Choose contestant</option>@foreach ($users->where('role', 'contestant')->where('is_active', true)->filter(fn ($candidate) => $candidate->activeTeams->isEmpty()) as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->username }}</option>@endforeach</select><button class="small-action" type="submit">Create team</button>@error('name')<span class="field-error">{{ $message }}</span>@enderror @error('captain_id')<span class="field-error">{{ $message }}</span>@enderror</form>
        @if ($teams->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">∅</span><div><h3>No teams have been created</h3><p>Team rankings and membership stay empty until teams exist.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table"><thead><tr><th>TEAM</th><th>CAPTAIN</th><th>ACTIVE MEMBERS</th><th>MEMBERSHIP</th><th>ACTIONS</th></tr></thead><tbody>
                @foreach ($teams as $team)
                    <tr>
                        <td>{{ $team->name }}</td>
                        <td>{{ $team->captain?->username ?? 'Unassigned' }}</td>
                        <td>{{ $team->members_count }}</td>
                        <td>{{ $team->activeMembers->pluck('username')->join(', ') ?: 'No members' }}</td>
                        <td>
                            <form class="management-form" method="POST" action="{{ route('admin.teams.destroy', $team) }}" onsubmit="return confirm(&quot;Are you sure you want to delete team '{{ $team->name }}'? This action cannot be undone.&quot;);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="small-action small-action-danger" aria-label="Delete team {{ $team->name }}">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><div class="eyebrow">MEMBERSHIP RECORD <span class="eyebrow-divider">/</span> 04</div><h2>Membership history</h2></div><span class="admin-section-note">LATEST 50 RECORDS</span></div>
        @if ($membershipHistory->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">≡</span><div><h3>No membership history yet</h3><p>Team joins, leaves, and removals are retained here.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table"><thead><tr><th>MEMBER</th><th>TEAM</th><th>ROLE</th><th>JOINED</th><th>LEFT</th><th>ACTIONS</th></tr></thead><tbody>
                @foreach ($membershipHistory as $membership)
                    <tr>
                        <td>{{ $membership->user?->username ?? 'Former account' }}</td>
                        <td>{{ $membership->team?->name ?? 'Closed team' }}</td>
                        <td>{{ ucfirst($membership->role) }}</td>
                        <td>{{ $membership->joined_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $membership->left_at?->format('Y-m-d H:i') ?? 'Active' }}</td>
                        <td>
                            <form class="management-form" method="POST" action="{{ route('admin.memberships.destroy', $membership) }}" onsubmit="return confirm('Are you sure you want to remove the membership record for member \'{{ $membership->user?->username ?? 'Former account' }}\' in team \'{{ $membership->team?->name ?? 'Closed team' }}\' (Role: {{ ucfirst($membership->role) }})? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="small-action small-action-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="admin-section">
        <div class="section-heading"><div><div class="eyebrow">CONTROLS <span class="eyebrow-divider">/</span> 05</div><h2>Challenges &amp; scoring</h2></div><span class="future-badge state-active">ACTIVE CONTROLS</span></div>
        <div class="future-grid">
            <div>
                <strong>Challenge management</strong>
                <span>Publishing is not available in this phase.</span>
                <small class="state-label">{{ \App\Models\Challenge::count() }} challenges configured</small>
            </div>
            <div>
                <strong>Scoring review</strong>
                <span>Only server-verified solves will count.</span>
                <small class="state-label">{{ \App\Models\VerifiedSolve::count() }} verified solve(s) · {{ number_format(\App\Models\VerifiedSolve::sum('points_awarded')) }} points</small>
            </div>
            <div>
                <strong>Recalculation &amp; resets</strong>
                <span>Requires confirmation and an administrative audit record.</span>
                <div style="display: flex; gap: 8px; margin-top: 8px; flex-wrap: wrap;">
                    <form method="POST" action="{{ route('admin.scores.recalculate') }}" onsubmit="return confirm('Recalculate competition scores and rankings from all legitimate server-verified solve records?');">
                        @csrf
                        <button type="submit" class="small-action">Recalculate Scores</button>
                    </form>
                    <form method="POST" action="{{ route('admin.scores.reset') }}" onsubmit="return confirm('WARNING: Reset all test solves and submissions? This will remove all verified solve records and test submission logs, resetting individual and team leaderboards to 0. Challenge definitions, flags, teams, and user accounts will NOT be deleted. Are you sure you want to proceed?');">
                        @csrf
                        <button type="submit" class="small-action small-action-danger">Reset Test Solves</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-section audit-section">
        <div class="section-heading"><div><div class="eyebrow">CHANGE RECORD <span class="eyebrow-divider">/</span> 06</div><h2>Administrative audit log</h2></div></div>
        @if ($auditLogs->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">≡</span><div><h3>No administrative actions recorded</h3><p>Role, status, team, and membership changes are recorded here.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table"><thead><tr><th>TIME</th><th>ACTOR</th><th>ACTION</th><th>SUBJECT / DETAILS</th></tr></thead><tbody>
                @foreach ($auditLogs as $log)
                    <tr><td>{{ $log->created_at->format('Y-m-d H:i') }}</td><td>{{ $log->actor?->username ?? 'System' }}</td><td><code>{{ $log->action }}</code></td><td>{{ $log->subject?->username ?? ($log->details['username'] ?? $log->details['name'] ?? '—') }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
