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
                <article class="challenge-card @if ($solved) is-solved @endif" data-challenge-id="{{ $challenge->id }}">
                    <div class="challenge-card-top">
                        <span class="challenge-category">{{ $category }}</span>
                        <span class="challenge-difficulty difficulty-{{ $challenge->difficulty }}">{{ $difficulty }}</span>
                    </div>
                    <h2 class="challenge-title">
                        <a href="{{ route('challenges.show', ['challenge' => $challenge]) }}">{{ $challenge->title }}</a>
                    </h2>
                    <p class="challenge-teaser">{{ $challenge->description ? str((string) $challenge->description)->limit(160) : 'No description provided.' }}</p>
                    <div class="challenge-card-foot" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                        <span class="challenge-points">{{ number_format($challenge->points) }} pts</span>
                        @if (isset($firstBloods[$challenge->id]))
                            <span style="font-family: var(--mono); font-size: 10px; color: #ff5555; background: rgba(255, 68, 68, 0.1); padding: 2px 6px; border-radius: 3px; border: 1px solid rgba(255, 68, 68, 0.3);" title="First Blood captured by {{ $firstBloods[$challenge->id]->user?->callsign }}">
                                🩸 {{ $firstBloods[$challenge->id]->user?->callsign }}
                            </span>
                        @endif
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

    @push('modals')
        <div class="challenge-modal-backdrop" id="challenge-modal" data-list-url="{{ route('challenges') }}" @unless (isset($openChallenge)) hidden @endunless>
            <div class="challenge-modal-dialog" id="challenge-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="challenge-modal-title" tabindex="-1">
                @isset($openChallenge)
                    @include('challenges._modal', $modalData)
                @endisset
            </div>
        </div>
    @endpush
@endsection

@push('scripts')
    <script src="{{ asset('js/challenges.js') }}" defer></script>
@endpush
