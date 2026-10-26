@extends('layouts.app')

@section('title', 'Profile')
@section('section', 'PROFILE')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif

    <section class="page-intro">
        <div class="eyebrow">OPERATIVE FILE <span class="eyebrow-divider">/</span> {{ $user->isAdmin() ? '03' : '04' }}</div>
        <h1>Your profile</h1>
        <p>Identity, callsign and field record for this cyber range.</p>
    </section>

    {{-- ── Operative ID card ─────────────────────────────────────── --}}
    <section class="op-card" aria-label="Operative identity">
        <div class="op-card-scan" aria-hidden="true"></div>
        <div class="op-avatar">
            <span>{{ strtoupper(substr($user->callsign, 0, 2)) }}</span>
            <i class="op-avatar-ring" aria-hidden="true"></i>
        </div>
        <div class="op-identity">
            <div class="op-tags">
                <span class="op-tag {{ $user->isAdmin() ? 'op-tag-admin' : 'op-tag-role' }}">{{ $user->isAdmin() ? 'ADMINISTRATOR' : 'CONTESTANT' }}</span>
                <span class="op-tag op-tag-live"><span class="status-dot"></span>{{ $user->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                @if ($user->google_id)
                    <span class="op-tag">GOOGLE LINKED</span>
                @endif
            </div>
            <h2 class="op-callsign">{{ $user->callsign }}</h2>
            <p class="op-handle">{{ '@' . $user->username }} <span>·</span> {{ $user->email }}</p>
        </div>
        <div class="op-id-code" aria-hidden="true">
            <span>OPERATIVE ID</span>
            <strong>P4DC-{{ str_pad((string) $user->id, 4, '0', STR_PAD_LEFT) }}</strong>
            <small>SINCE {{ strtoupper($user->created_at->format('M Y')) }}</small>
        </div>
    </section>

    {{-- ── Telemetry ─────────────────────────────────────────────── --}}
    <section class="op-stats" aria-label="Field record">
        <article class="op-stat op-stat-accent">
            <span>PERSONAL VERIFIED POINTS</span>
            <strong>{{ number_format($points) }}</strong>
            <small>Server-verified score</small>
        </article>
        <article class="op-stat">
            <span>FLAGS CAPTURED</span>
            <strong>{{ number_format($flags) }}</strong>
            <small>{{ $flags === 1 ? 'Mission completed' : 'Missions completed' }}</small>
        </article>
        <article class="op-stat">
            <span>INDIVIDUAL RANK</span>
            <strong>{{ $rank ? '#' . str_pad((string) $rank, 2, '0', STR_PAD_LEFT) : '—' }}</strong>
            <small>{{ $rank ? 'Live range standing' : ($user->isAdmin() ? 'Admins are not ranked' : 'Capture a flag to rank') }}</small>
        </article>
        <article class="op-stat">
            <span>ACTIVE TEAM</span>
            @if ($user->isAdmin())
                <strong class="op-stat-text">Admin</strong>
                <small>Administrator account</small>
            @elseif ($team)
                <strong class="op-stat-text"><a href="{{ route('teams.show', $team) }}">{{ $team->name }}</a></strong>
                <small>{{ $teamMemberCount }} / {{ \App\Models\Team::MAX_MEMBERS }} operatives</small>
            @else
                <strong class="op-stat-text">Solo</strong>
                <small><a class="text-link" href="{{ route('teams.index') }}">Find or create a team →</a></small>
            @endif
        </article>
    </section>

    <div class="op-grid">
        {{-- ── Callsign configuration ───────────────────────────── --}}
        <section class="op-panel">
            <div class="op-panel-head">
                <div class="eyebrow">CALLSIGN CONFIGURATION</div>
                <h3>Hacker callsign</h3>
                <p>Shown on your team roster and the live CTF leaderboard. Examples: <code>krypt</code>, <code>cyber</code>, <code>zero_cool</code>.</p>
            </div>
            <form class="op-form" method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PATCH')
                <label for="nickname">Callsign / nickname</label>
                <div class="op-input-row">
                    <span class="op-input-prefix" aria-hidden="true">&gt;_</span>
                    <input id="nickname" name="nickname" type="text" value="{{ old('nickname', $user->nickname) }}" placeholder="krypt" maxlength="50" autocomplete="off" spellcheck="false">
                </div>
                @error('nickname')<p class="field-error op-error">{{ $message }}</p>@enderror
                <p class="op-hint">Letters, numbers, <code>_ . - |</code> only. Leave empty to use your username.</p>
                <button class="button button-primary op-save" type="submit">Save callsign <span aria-hidden="true">→</span></button>
            </form>
        </section>

        {{-- ── Account record ───────────────────────────────────── --}}
        <section class="op-panel">
            <div class="op-panel-head">
                <div class="eyebrow">ACCOUNT RECORD</div>
                <h3>Identity details</h3>
            </div>
            <dl class="op-details">
                <div><dt>USERNAME</dt><dd>{{ $user->username }}</dd></div>
                <div><dt>CALLSIGN</dt><dd>{{ $user->nickname ?: 'Not set' }}</dd></div>
                <div><dt>EMAIL</dt><dd>{{ $user->email }}</dd></div>
                <div><dt>ROLE</dt><dd>{{ ucfirst($user->role) }}</dd></div>
                <div><dt>MEMBER SINCE</dt><dd>{{ $user->created_at->format('M d, Y') }}</dd></div>
            </dl>
        </section>
    </div>

    {{-- ── Recent captures ───────────────────────────────────────── --}}
    @unless ($user->isAdmin())
        <section class="admin-section op-captures">
            <div class="section-heading">
                <div><div class="eyebrow">FIELD LOG <span class="eyebrow-divider">/</span> RECENT</div><h2>Recent flag captures</h2></div>
                <a class="text-link" href="{{ route('challenges') }}">Open challenges →</a>
            </div>
            @if ($recentSolves->isEmpty())
                <div class="empty-state"><span class="empty-symbol">⚑</span><div><h3>No flags captured yet.</h3><p>Enter the range and submit your first flag.</p></div><span class="empty-code">LOG / 000</span></div>
            @else
                <div class="table-scroll"><table class="admin-table"><thead><tr><th>MISSION</th><th>CATEGORY</th><th>POINTS</th><th>CAPTURED</th></tr></thead><tbody>
                    @foreach ($recentSolves as $solve)
                        <tr>
                            <td><strong>{{ $solve->challenge?->title ?? 'Retired mission' }}</strong></td>
                            <td><small>{{ $solve->challenge ? config('ctf.categories.' . $solve->challenge->category, $solve->challenge->category) : '—' }}</small></td>
                            <td class="rank-cell">+{{ number_format($solve->points_awarded) }}</td>
                            <td><small>{{ $solve->verified_at?->format('M d · H:i') }}</small></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </section>
    @endunless
@endsection
