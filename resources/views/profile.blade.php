@extends('layouts.app')

@section('title', 'Profile')
@section('section', 'PROFILE')

@section('content')
    @if (session('status'))
        <p class="form-notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <div class="form-notice form-notice-error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <section class="page-intro">
        <div class="eyebrow">OPERATIVE FILE <span class="eyebrow-divider">/</span> 04</div>
        <h1>Your profile</h1>
        <p>Account details and operative callsign for this cyber range.</p>
    </section>

    <div style="display: grid; gap: 24px; max-width: 720px;">
        <section class="profile-sheet">
            <div class="profile-monogram">{{ strtoupper(substr(auth()->user()->callsign, 0, 1)) }}</div>
            <dl>
                <div>
                    <dt>CALLSIGN / NICKNAME</dt>
                    <dd>
                        <strong style="color: var(--acid); font-size: 16px;">{{ auth()->user()->nickname ?: 'Not set (using username)' }}</strong>
                        @if (auth()->user()->nickname)
                            <small style="color: var(--muted); margin-left: 8px;">({{ '@' . auth()->user()->username }})</small>
                        @endif
                    </dd>
                </div>
                <div><dt>USERNAME</dt><dd>{{ auth()->user()->username }}</dd></div>
                <div><dt>EMAIL</dt><dd>{{ auth()->user()->email }}</dd></div>
                <div><dt>MEMBER SINCE</dt><dd>{{ auth()->user()->created_at->format('M Y') }}</dd></div>
                <div>
                    <dt>ACTIVE TEAM</dt>
                    <dd>
                        @if (auth()->user()->isAdmin())
                            Administrator account
                        @else
                            @forelse (auth()->user()->activeTeams as $team)
                                <a class="text-link" href="{{ route('teams.show', $team) }}">{{ $team->name }} ({{ $team->activeMembers()->count() }}/4 members)</a>
                            @empty
                                <a class="text-link" href="{{ route('teams.index') }}">Optional · find or create a team (max 4 members)</a>
                            @endforelse
                        @endif
                    </dd>
                </div>
                <div><dt>PERSONAL VERIFIED POINTS</dt><dd>{{ number_format(auth()->user()->verifiedSolves()->sum('points_awarded')) }}</dd></div>
            </dl>
        </section>

        <section class="team-panel">
            <div class="section-heading" style="padding-top: 0;">
                <div>
                    <div class="eyebrow">CALLSIGN CONFIGURATION</div>
                    <h2>Update Operative Nickname</h2>
                </div>
            </div>
            <p style="color: var(--muted); font-size: 13px; margin: 0 0 16px;">
                Choose your in-range hacker callsign (e.g. <code>krypt</code>, <code>cyber</code>, <code>zero_cool</code>, <code>matrix</code>). This will be displayed on the team roster and live CTF leaderboards.
            </p>
            <form class="team-entry-form" method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PATCH')
                <label for="nickname" style="display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600;">Hacker Callsign / Nickname</label>
                <input id="nickname" name="nickname" type="text" value="{{ old('nickname', auth()->user()->nickname) }}" placeholder="e.g. krypt, cyber, spectre" maxlength="50" style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid var(--line-bright); background: #0e1320; color: var(--text); border-radius: 4px;">
                @error('nickname')<span class="field-error" style="display: block; margin-top: 6px;">{{ $message }}</span>@enderror
                <button class="button button-outline" type="submit" style="margin-top: 14px;">Save callsign</button>
            </form>
        </section>
    </div>
@endsection
