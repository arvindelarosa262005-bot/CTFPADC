@extends('layouts.app')

@section('title', 'Home')
@section('section', 'HOME')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif
    <section class="welcome-row">
        <div>
            <div class="eyebrow">MISSION CONTROL <span class="eyebrow-divider">/</span> YOUR RANGE</div>
            <h1>Good to see you, <span>{{ auth()->user()->username }}</span>.</h1>
        </div>
        <div class="date-stamp"><span class="status-dot"></span> ACCOUNT ACTIVE</div>
    </section>

    <section class="hero-panel">
        <div class="hero-copy">
            <div class="hero-kicker"><span>01</span> THE P4DC RANGE</div>
            <h2>Think like an attacker.<br><em>Build like a defender.</em></h2>
            <p>A practical cybersecurity playground for curious minds. Sharpen your skills, follow the signal, and learn by doing.</p>
            <a class="button button-outline" href="{{ route('challenges') }}">Enter the range <span aria-hidden="true">→</span></a>
        </div>
        <div class="hero-signal" aria-hidden="true">
            <div class="signal-ring signal-ring-one"></div><div class="signal-ring signal-ring-two"></div>
            <div class="signal-crosshair">P4DC<span> / 01</span></div>
            <div class="signal-coordinate coordinate-top">51° 30′ 26.4″ N</div>
            <div class="signal-coordinate coordinate-bottom">001° 07′ 39.6″ W</div>
        </div>
    </section>

    <section class="stats-grid contestant-stats" aria-label="Challenge availability">
        <article class="stat-block"><div class="stat-top"><span>AVAILABLE CHALLENGES</span></div><strong>{{ number_format($availableChallenges) }}</strong><p>{{ $availableChallenges === 1 ? 'Mission published' : 'Missions published' }}</p></article>
        <article class="stat-block"><div class="stat-top"><span>CHALLENGES SOLVED</span></div><strong>{{ number_format($solvedCount) }}</strong><p>{{ $solvedCount === 1 ? 'Mission completed' : 'Missions completed' }}</p></article>
    </section>

    <section class="dashboard-lower">
        <div class="section-heading"><div><div class="eyebrow">FIELD NOTES <span class="eyebrow-divider">/</span> 01</div><h2>Challenge feed</h2></div><a class="text-link" href="{{ route('challenges') }}">View all <span aria-hidden="true">→</span></a></div>
        @if ($recentChallenges->isEmpty())
            <div class="empty-state"><span class="empty-symbol">∅</span><div><h3>No challenges available yet.</h3><p>New missions will appear here when they are published.</p></div><span class="empty-code">FEED / 000</span></div>
        @else
            <div class="table-scroll"><table class="admin-table"><thead><tr><th>MISSION</th><th>DIFFICULTY</th><th>POINTS</th></tr></thead><tbody>
                @foreach ($recentChallenges as $recent)
                    <tr><td><strong><a href="{{ route('challenges.show', ['challenge' => $recent]) }}">{{ $recent->title }}</a></strong><small>{{ config('ctf.categories.' . $recent->category, $recent->category) }}</small></td><td>{{ config('ctf.difficulties.' . $recent->difficulty, $recent->difficulty) }}</td><td class="rank-cell">{{ number_format($recent->points) }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
