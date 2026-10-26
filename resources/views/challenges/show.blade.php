@extends('layouts.app')

@section('title', 'Challenges')
@section('section', 'CHALLENGES')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif

    <section class="page-intro">
        <div class="eyebrow">THE RANGE <span class="eyebrow-divider">/</span> 02</div>
        <h1>Challenges</h1>
        <p>Published missions for this range. Open a mission to view its target and submit a flag.</p>
    </section>

    @if ($challenges->isEmpty())
        <section class="empty-state empty-state-large">
            <span class="empty-symbol">∅</span>
            <div>
                <div class="eyebrow">NO MISSIONS PUBLISHED</div>
                <h2>No challenges available yet.</h2>
                <p>Check back when the first missions are released.</p>
            </div>
        </section>
    @else
        <section class="challenge-grid" aria-label="Published challenges">
            @foreach ($challenges as $challenge)
                @php
                    $solved = in_array($challenge->id, $solvedIds, true);
                    $category = config('ctf.categories.' . $challenge->category, $challenge->category);
                    $difficulty = config('ctf.difficulties.' . $challenge->difficulty, $challenge->difficulty);
                @endphp
                <article class="challenge-card @if ($solved) is-solved @endif">
                    <div class="challenge-card-top">
                        <span class="challenge-category">{{ $category }}</span>
                        <span class="challenge-difficulty difficulty-{{ $challenge->difficulty }}">{{ $difficulty }}</span>
                    </div>
                    <h2 class="challenge-title">
                        <a href="{{ route('challenges.show', ['challenge' => $challenge]) }}">{{ $challenge->title }}</a>
                    </h2>
                    <p class="challenge-teaser">{{ $challenge->description ? str((string) $challenge->description)->limit(160) : 'No description provided.' }}</p>
                    <div class="challenge-card-foot">
                        <span class="challenge-points">{{ number_format($challenge->points) }} pts</span>
                        @if ($solved)
                            <span class="challenge-solved-badge">Solved</span>
                        @else
                            <span class="challenge-open-badge">Open</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
    @endif
@endsection
