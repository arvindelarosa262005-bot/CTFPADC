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
        <article class="admin-stat"><span>REGISTERED USERS</span><strong id="stat-registered-users">{{ number_format($registeredUsers) }}</strong><small>Accounts in this range</small></article>
        <article class="admin-stat"><span>EXISTING TEAMS</span><strong id="stat-existing-teams">{{ number_format($existingTeams) }}</strong><small>Max 4 operatives per squad</small></article>
        <article class="admin-stat points-card" id="card-points-earned">
            <span>POINTS EARNED</span>
            <strong id="stat-points-earned">{{ $totalPointsEarned > 0 ? number_format($totalPointsEarned) : '000' }}</strong>
            <small id="stat-points-note">{{ $totalPointsEarned > 0 ? 'Verified points across ' . number_format($totalFlagsCaptured) . ' captured flag(s)' : 'Ready to be earned' }}</small>
            <i id="stat-points-badge">{{ $totalPointsEarned > 0 ? 'REAL-TIME SCORING TELEMETRY ACTIVE' : 'PLACEHOLDER · VERIFIED SCORING NOT ACTIVE' }}</i>
        </article>
        <article class="admin-stat" style="border-color: rgba(0, 240, 255, 0.35);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>RANGE CHALLENGES</span>
                <span class="status-dot" style="background: #00ff9d; box-shadow: 0 0 8px #00ff9d;"></span>
            </div>
            <strong style="color: #00ff9d;">{{ $liveChallengesCount }} <span style="font-size: 13px; color: var(--muted); font-weight: 500;">/ {{ $totalChallengesCount }} LIVE</span></strong>
            <small><a href="{{ route('admin.challenges.index') }}" style="color: var(--neon-cyan); text-decoration: none; font-weight: 600;">Control &amp; Release Challenges →</a></small>
            <i style="color: {{ $liveChallengesCount > 0 ? 'var(--neon-cyan)' : 'var(--amber)' }}; font-style: normal; font-size: 10px; font-family: var(--mono); letter-spacing: 0.05em;">
                {{ $totalChallengesCount - $liveChallengesCount }} CHALLENGE(S) STAGED/HIDDEN
            </i>
        </article>
    </section>

    <!-- Real-Time CTF Leaderboard & Solves Monitor -->
    <div class="live-monitor-banner">
        <div class="live-monitor-status">
            <span class="status-dot"></span>
            <span>LIVE CTF RADAR &amp; LEADERBOARD</span>
            <span class="live-sync-indicator" id="live-sync-indicator">LIVE AUTO-SYNC: 5S</span>
        </div>
        <div class="live-monitor-actions">
            <span class="last-sync-time" id="last-sync-time">SYNCED: {{ now()->format('H:i:s') }}</span>
            <button type="button" class="small-action" id="btn-manual-sync">⚡ Sync now</button>
            <button type="button" class="small-action" id="btn-toggle-sync" data-active="true">Pause</button>
        </div>
    </div>

    <!-- Live Recent Flag Captures Stream -->
    <div class="realtime-ticker-box">
        <div class="ticker-header">
            <span><span class="status-dot" style="width:6px;height:6px;display:inline-block;margin-right:6px;"></span>LIVE FLAG CAPTURE STREAM</span>
            <span>REAL-TIME VERIFICATION FEED</span>
        </div>
        <div class="ticker-stream" id="ticker-stream">
            @forelse ($recentSolves as $solve)
                <div class="ticker-item">
                    <span class="ticker-time">{{ $solve->verified_at?->format('H:i:s') }}</span>
                    <span class="ticker-badge">+{{ $solve->points_awarded }} PTS</span>
                    <span class="ticker-user">{{ $solve->user?->nickname ?: $solve->user?->username }} @if($solve->user?->nickname)<small style="color:var(--muted);font-weight:400;">({{ '@' . $solve->user?->username }})</small>@endif</span>
                    <span class="ticker-arrow">captured</span>
                    <span class="ticker-challenge">{{ $solve->challenge?->title }}</span>
                    <span class="ticker-team">[{{ $solve->teamAtSolve?->name ?? 'Solo' }}]</span>
                </div>
            @empty
                <div class="ticker-item ticker-empty" id="ticker-empty-placeholder">Waiting for incoming flag submissions in range...</div>
            @endforelse
        </div>
    </div>

    <section class="admin-section" id="individual-rankings">
        <div class="section-heading"><div><div class="eyebrow">VERIFIED SOLVES <span class="eyebrow-divider">/</span> 01</div><h2>Individual Top Scorers</h2></div><span class="admin-section-note">POINTS DESC · USERNAME ASC</span></div>
        @if ($individualScorers->isEmpty())
            <div class="empty-state admin-empty" id="empty-individual-scorers"><span class="empty-symbol">#</span><div><h3>No scores available yet</h3><p>Individual rankings will appear after verified solves are recorded.</p></div></div>
            <div class="table-scroll" id="wrap-individual-scorers" style="display: none;"><table class="admin-table"><thead><tr><th>RANK</th><th>OPERATIVE / CALLSIGN</th><th>USERNAME</th><th>TEAM</th><th>VERIFIED POINTS</th><th>FLAGS CAPTURED</th><th>ACTIONS</th></tr></thead><tbody id="live-individual-body"></tbody></table></div>
        @else
            <div class="table-scroll" id="wrap-individual-scorers"><table class="admin-table"><thead><tr><th>RANK</th><th>OPERATIVE / CALLSIGN</th><th>USERNAME</th><th>TEAM</th><th>VERIFIED POINTS</th><th>FLAGS CAPTURED</th><th>ACTIONS</th></tr></thead><tbody id="live-individual-body">
                @foreach ($individualScorers as $index => $scorer)
                    <tr data-user-id="{{ $scorer->id }}">
                        <td class="rank-cell">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>
                            <strong style="color: var(--acid);">{{ $scorer->nickname ?: $scorer->username }}</strong>
                        </td>
                        <td><span style="font-family: var(--mono); color: var(--muted);">{{ '@' . $scorer->username }}</span></td>
                        <td><span style="font-size: 11px; color: var(--muted);">{{ $scorer->activeTeams->first()?->name ?? 'Solo / No team' }}</span></td>
                        <td style="font-family: var(--mono); font-weight: 600; color: var(--emerald);">{{ number_format($scorer->total_verified_points ?? 0) }}</td>
                        <td style="font-family: var(--mono);">{{ number_format($scorer->challenges_solved) }}</td>
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

    <section class="admin-section" id="team-rankings">
        <div class="section-heading"><div><div class="eyebrow">TEAM SOLVES <span class="eyebrow-divider">/</span> 02</div><h2>Team Scorers</h2></div><span class="admin-section-note">POINTS DESC · TEAM NAME ASC</span></div>
        @if ($teamScorers->isEmpty())
            <div class="empty-state admin-empty" id="empty-team-scorers"><span class="empty-symbol">#</span><div><h3>No scores available yet</h3><p>Team rankings will appear after verified team solves are recorded.</p></div></div>
            <div class="table-scroll" id="wrap-team-scorers" style="display: none;"><table class="admin-table"><thead><tr><th>TEAM RANK</th><th>TEAM NAME</th><th>VERIFIED TEAM POINTS</th><th>FLAGS CAPTURED</th><th>MEMBERS (MAX 4)</th><th>CAPTAIN CALLSIGN</th></tr></thead><tbody id="live-team-body"></tbody></table></div>
        @else
            <div class="table-scroll" id="wrap-team-scorers"><table class="admin-table"><thead><tr><th>TEAM RANK</th><th>TEAM NAME</th><th>VERIFIED TEAM POINTS</th><th>FLAGS CAPTURED</th><th>MEMBERS (MAX 4)</th><th>CAPTAIN CALLSIGN</th></tr></thead><tbody id="live-team-body">
                @foreach ($teamScorers as $index => $team)
                    <tr data-team-id="{{ $team->id }}">
                        <td class="rank-cell">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</td>
                        <td><strong>{{ $team->name }}</strong></td>
                        <td style="font-family: var(--mono); font-weight: 600; color: var(--emerald);">{{ number_format($team->total_verified_points ?? 0) }}</td>
                        <td style="font-family: var(--mono);">{{ number_format($team->challenges_solved) }}</td>
                        <td><span class="state-label">{{ $team->members_count }} / 4</span></td>
                        <td><span style="color: var(--acid); font-weight: 600;">{{ $team->captain?->nickname ?: $team->captain?->username ?? '—' }}</span></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>

    <section class="admin-section" id="account-management">
        <div class="section-heading"><div><div class="eyebrow">IDENTITIES <span class="eyebrow-divider">/</span> 03</div><h2>User accounts &amp; memberships</h2></div><span class="admin-section-note">{{ $users->count() }} ACCOUNTS</span></div>
        @if ($users->isEmpty())
            <div class="empty-state admin-empty"><span class="empty-symbol">∅</span><div><h3>No user accounts yet</h3><p>Registered contestants will appear here.</p></div></div>
        @else
            <div class="table-scroll"><table class="admin-table management-table"><thead><tr><th>ACCOUNT</th><th>ROLE</th><th>STATUS</th><th>TEAM MEMBERSHIP (MAX 4)</th><th>ACTIONS</th></tr></thead><tbody>
                @foreach ($users as $managedUser)
                    <tr>
                        <td>
                            <strong>{{ $managedUser->nickname ?: $managedUser->username }}</strong>
                            <small>{{ '@' . $managedUser->username }} · {{ $managedUser->email }}</small>
                        </td>
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
                        <td><form class="management-form" method="POST" action="{{ route('admin.users.team', $managedUser) }}">@csrf @method('PATCH')<select name="team_id" aria-label="Team for {{ $managedUser->username }}"><option value="">No team</option>@foreach ($teams as $team)<option value="{{ $team->id }}" @selected($managedUser->activeTeams->contains('id', $team->id))>{{ $team->name }} ({{ $team->members_count }}/4)</option>@endforeach</select><button type="submit" class="small-action">Save</button></form></td>
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

    <!-- ── AI Anti-Cheat & Integrity Sentinel ──────────────────────────────── -->
    <section class="admin-section" style="border: 1px solid rgba(255, 68, 68, 0.35); background: rgba(20, 10, 15, 0.6); padding: 22px; border-radius: 8px; margin-bottom: 24px;">
        <div class="section-heading" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 18px;">
            <div>
                <div class="eyebrow" style="color: #ff5555;"><span class="status-dot" style="background:#ff5555; box-shadow:0 0 8px #ff5555; display:inline-block; margin-right:6px;"></span>INTEGRITY RADAR <span class="eyebrow-divider">/</span> AI DETECTION</div>
                <h2 style="color: #fff; font-size: 20px; margin: 4px 0 0;">🤖 AI &amp; Anti-Cheat Surveillance Sentinel</h2>
                <p style="color: var(--muted); font-size: 13px; margin: 4px 0 0;">
                    Real-time monitoring laban sa paggamit ng ChatGPT, Claude, at LLM prompt injections gamit ang Canary Honeypots at Client Telemetry.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <div style="padding: 6px 12px; background: rgba(255, 68, 68, 0.15); border: 1px solid rgba(255, 68, 68, 0.4); border-radius: 4px; font-family: var(--mono); font-size: 11px; color: #ff5555;">
                    CRITICAL CANARY TRIPS: <strong>{{ $criticalAiIncidents }}</strong>
                </div>
                <div style="padding: 6px 12px; background: rgba(0, 240, 255, 0.1); border: 1px solid rgba(0, 240, 255, 0.3); border-radius: 4px; font-family: var(--mono); font-size: 11px; color: var(--neon-cyan);">
                    TOTAL LOGGED INCIDENTS: <strong>{{ $totalAiIncidents }}</strong>
                </div>
            </div>
        </div>

        <!-- Flagged Operatives Table -->
        <h3 style="font-size: 14px; color: #ff9999; margin: 0 0 10px; font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.05em;">
            🚨 Flagged / Suspected Operatives ({{ $flaggedUsers->count() }})
        </h3>
        @if ($flaggedUsers->isEmpty())
            <div class="empty-state admin-empty" style="padding: 20px; background: rgba(0, 255, 157, 0.04); border-color: rgba(0, 255, 157, 0.2);">
                <span class="empty-symbol" style="color: #00ff9d;">🛡️</span>
                <div>
                    <h3 style="color: #00ff9d;">All operatives currently clean</h3>
                    <p style="color: var(--muted); font-size: 12px;">Walang na-detect na AI honeypot triggers o anomalous clipboard paste speeds.</p>
                </div>
            </div>
        @else
            <div class="table-scroll" style="margin-bottom: 20px;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>OPERATIVE</th>
                            <th>TEAM</th>
                            <th>AI SUSPICION SCORE</th>
                            <th>PRIMARY EVIDENCE / REASON</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($flaggedUsers as $flagged)
                            <tr style="background: rgba(255, 68, 68, 0.05);">
                                <td>
                                    <strong style="color: #ff5555;">{{ $flagged->callsign }}</strong>
                                    <small style="display: block; color: var(--faint);">{{ $flagged->email }}</small>
                                </td>
                                <td>{{ $flagged->activeTeams->first()?->name ?? 'Solo Operative' }}</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 80px; height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden;">
                                            <div style="width: {{ $flagged->ai_suspicion_score }}%; height: 100%; background: {{ $flagged->ai_suspicion_score >= 70 ? '#ff4444' : '#ffb86c' }};"></div>
                                        </div>
                                        <span style="font-family: var(--mono); font-weight: 700; color: {{ $flagged->ai_suspicion_score >= 70 ? '#ff5555' : '#ffb86c' }};">
                                            {{ $flagged->ai_suspicion_score }}%
                                        </span>
                                        @if ($flagged->is_ai_flagged)
                                            <span class="state-label" style="background: rgba(255, 68, 68, 0.2); color: #ff5555; border-color: rgba(255,68,68,0.5);">CONFIRMED AI</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span style="color: #fff; font-size: 12px;">{{ $flagged->ai_flag_reason ?: 'Elevated anomalous telemetry' }}</span>
                                </td>
                                <td>
                                    <div class="member-actions">
                                        <form method="POST" action="{{ route('admin.users.ai_clear', $flagged) }}">
                                            @csrf
                                            <button class="small-action" type="submit" title="Reset suspicion score and clear flag">✅ Clear Flag</button>
                                        </form>
                                        @if ($flagged->is_active && ! $flagged->isAdmin())
                                            <form method="POST" action="{{ route('admin.users.ai_disqualify', $flagged) }}" onsubmit="return confirm('Sigurado ka bang idi-DISQUALIFY at idi-deactivate ang account ni {{ $flagged->callsign }} dahil sa paggamit ng AI?');">
                                                @csrf
                                                <button class="small-action small-action-danger" type="submit">🚫 Disqualify</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <!-- AI Telemetry Event Stream -->
        <h3 style="font-size: 14px; color: var(--neon-cyan); margin: 18px 0 10px; font-family: var(--mono); text-transform: uppercase; letter-spacing: 0.05em;">
            📡 Live AI Detection Telemetry Feed
        </h3>
        @if ($aiLogs->isEmpty())
            <div style="padding: 12px; background: rgba(0,0,0,0.3); border: 1px solid var(--line); border-radius: 4px; font-size: 12px; color: var(--muted); font-family: var(--mono);">
                No AI telemetry incidents logged yet.
            </div>
        @else
            <div class="table-scroll">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>TIME</th>
                            <th>OPERATIVE</th>
                            <th>CHALLENGE</th>
                            <th>SEVERITY</th>
                            <th>INCIDENT DETAILS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($aiLogs as $log)
                            <tr>
                                <td style="font-family: var(--mono); font-size: 11px;">{{ $log->created_at->format('H:i:s') }}</td>
                                <td>
                                    <strong style="color: #fff;">{{ $log->user?->callsign ?? 'Unknown' }}</strong>
                                </td>
                                <td>{{ $log->challenge?->title ?? 'Platform General' }}</td>
                                <td>
                                    @if ($log->severity === 'critical')
                                        <span class="state-label" style="background: rgba(255, 68, 68, 0.2); color: #ff5555; border-color: rgba(255,68,68,0.5); font-weight: 700;">CRITICAL</span>
                                    @elseif ($log->severity === 'high')
                                        <span class="state-label" style="background: rgba(255, 184, 108, 0.2); color: #ffb86c; border-color: rgba(255,184,108,0.5);">HIGH</span>
                                    @else
                                        <span class="state-label" style="color: var(--muted);">MEDIUM</span>
                                    @endif
                                </td>
                                <td style="font-size: 12px; color: var(--text);">
                                    {{ $log->details }}
                                    @if ($log->submitted_content && $log->submitted_content !== '[FLAG_HASHED]')
                                        <div style="font-family: var(--mono); font-size: 11px; color: #ffb86c; margin-top: 2px;">
                                            <code>{{ $log->submitted_content }}</code>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const liveEndpoint = "{{ route('admin.scores.live') }}";
    const deleteRouteBase = "{{ url('/admin/users') }}";
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    let isPolling = true;
    let pollInterval = null;

    const btnToggle = document.getElementById('btn-toggle-sync');
    const btnManual = document.getElementById('btn-manual-sync');
    const lastSyncEl = document.getElementById('last-sync-time');
    const indicatorEl = document.getElementById('live-sync-indicator');

    const individualBody = document.getElementById('live-individual-body');
    const wrapIndividual = document.getElementById('wrap-individual-scorers');
    const emptyIndividual = document.getElementById('empty-individual-scorers');

    const teamBody = document.getElementById('live-team-body');
    const wrapTeam = document.getElementById('wrap-team-scorers');
    const emptyTeam = document.getElementById('empty-team-scorers');

    const statPoints = document.getElementById('stat-points-earned');
    const statPointsNote = document.getElementById('stat-points-note');
    const statPointsBadge = document.getElementById('stat-points-badge');
    const tickerStream = document.getElementById('ticker-stream');

    function formatNumber(num) {
        return new Intl.NumberFormat().format(num || 0);
    }

    function pad2(num) {
        return String(num).padStart(2, '0');
    }

    async function fetchLiveScores() {
        try {
            const response = await fetch(liveEndpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!response.ok) return;
            const data = await response.json();

            // 1. Update stats
            if (data.stats) {
                if (statPoints) {
                    statPoints.textContent = data.stats.total_points > 0 ? formatNumber(data.stats.total_points) : '000';
                }
                if (statPointsNote) {
                    statPointsNote.textContent = data.stats.total_points > 0 
                        ? `Verified points across ${formatNumber(data.stats.total_flags)} captured flag(s)`
                        : 'Ready to be earned';
                }
                if (statPointsBadge) {
                    statPointsBadge.textContent = data.stats.total_points > 0 
                        ? 'REAL-TIME SCORING TELEMETRY ACTIVE'
                        : 'PLACEHOLDER · VERIFIED SCORING NOT ACTIVE';
                }
            }

            // 2. Update Individuals
            if (data.individuals && data.individuals.length > 0) {
                if (emptyIndividual) emptyIndividual.style.display = 'none';
                if (wrapIndividual) wrapIndividual.style.display = '';

                if (individualBody) {
                    individualBody.innerHTML = data.individuals.map(scorer => {
                        const deleteBtn = scorer.is_admin ? '<span class="state-label">Protected admin</span>' : `
                            <form class="management-form" method="POST" action="${deleteRouteBase}/${scorer.id}/solves" onsubmit="return confirm('Are you sure you want to delete verified solves for user \\'${scorer.username}\\'?');">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="small-action small-action-danger">Delete</button>
                            </form>
                        `;

                        return `
                            <tr data-user-id="${scorer.id}">
                                <td class="rank-cell">${pad2(scorer.rank)}</td>
                                <td>
                                    <strong style="color: var(--acid);">${scorer.nickname || scorer.username}</strong>
                                </td>
                                <td><span style="font-family: var(--mono); color: var(--muted);">@${scorer.username}</span></td>
                                <td><span style="font-size: 11px; color: var(--muted);">${scorer.team_name}</span></td>
                                <td style="font-family: var(--mono); font-weight: 600; color: var(--emerald);">${formatNumber(scorer.points)}</td>
                                <td style="font-family: var(--mono);">${formatNumber(scorer.solves_count)}</td>
                                <td>${deleteBtn}</td>
                            </tr>
                        `;
                    }).join('');
                }
            }

            // 3. Update Teams
            if (data.teams && data.teams.length > 0) {
                if (emptyTeam) emptyTeam.style.display = 'none';
                if (wrapTeam) wrapTeam.style.display = '';

                if (teamBody) {
                    teamBody.innerHTML = data.teams.map(team => `
                        <tr data-team-id="${team.id}">
                            <td class="rank-cell">${pad2(team.rank)}</td>
                            <td><strong>${team.name}</strong></td>
                            <td style="font-family: var(--mono); font-weight: 600; color: var(--emerald);">${formatNumber(team.points)}</td>
                            <td style="font-family: var(--mono);">${formatNumber(team.solves_count)}</td>
                            <td><span class="state-label">${team.members_count} / ${team.max_members}</span></td>
                            <td><span style="color: var(--acid); font-weight: 600;">${team.captain_callsign}</span></td>
                        </tr>
                    `).join('');
                }
            }

            // 4. Update Recent Solves Ticker
            if (data.recent_solves && data.recent_solves.length > 0 && tickerStream) {
                const placeholder = document.getElementById('ticker-empty-placeholder');
                if (placeholder) placeholder.remove();

                tickerStream.innerHTML = data.recent_solves.map(solve => `
                    <div class="ticker-item">
                        <span class="ticker-time">${solve.verified_at}</span>
                        <span class="ticker-badge">+${solve.points} PTS</span>
                        <span class="ticker-user">${solve.user_callsign} ${solve.username ? `<small style="color:var(--muted);font-weight:400;">(@${solve.username})</small>` : ''}</span>
                        <span class="ticker-arrow">captured</span>
                        <span class="ticker-challenge">${solve.challenge_title}</span>
                        <span class="ticker-team">[${solve.team_name}]</span>
                    </div>
                `).join('');
            }

            // 5. Update timestamp
            if (lastSyncEl) {
                lastSyncEl.textContent = `SYNCED: ${data.timestamp}`;
            }
        } catch (err) {
            console.error('Realtime CTF poll error:', err);
        }
    }

    function startPolling() {
        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(fetchLiveScores, 5000);
        isPolling = true;
        if (btnToggle) btnToggle.textContent = 'Pause';
        if (indicatorEl) indicatorEl.textContent = 'LIVE AUTO-SYNC: 5S';
    }

    function stopPolling() {
        if (pollInterval) clearInterval(pollInterval);
        isPolling = false;
        if (btnToggle) btnToggle.textContent = 'Resume';
        if (indicatorEl) indicatorEl.textContent = 'SYNC PAUSED';
    }

    if (btnToggle) {
        btnToggle.addEventListener('click', function () {
            if (isPolling) {
                stopPolling();
            } else {
                startPolling();
                fetchLiveScores();
            }
        });
    }

    if (btnManual) {
        btnManual.addEventListener('click', function () {
            fetchLiveScores();
        });
    }

    // Start auto polling
    startPolling();
});
</script>
@endpush
