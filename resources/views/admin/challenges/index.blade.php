@extends('layouts.app')

@section('title', 'Admin — Challenges')
@section('section', 'CHALLENGE MANAGEMENT')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="form-notice form-notice-error" role="alert">{{ $errors->first() }}</p>
    @endif

    <section class="welcome-row admin-welcome">
        <div>
            <div class="eyebrow">RESTRICTED ACCESS <span class="eyebrow-divider">/</span> CHALLENGES</div>
            <h1>Challenge management<span class="admin-heading-dot">.</span></h1>
        </div>
        <a class="button button-outline" href="{{ route('admin.challenges.create') }}">Create challenge <span aria-hidden="true">+</span></a>
    </section>

    @if ($challenges->isEmpty())
        <section class="empty-state admin-empty">
            <span class="empty-symbol">∅</span>
            <div>
                <h3>No challenges configured</h3>
                <p>Create the first challenge to publish it to the range.</p>
            </div>
        </section>
    @else
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>CHALLENGE</th>
                        <th>CATEGORY</th>
                        <th>DIFFICULTY</th>
                        <th>POINTS</th>
                        <th>TARGET</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($challenges as $challenge)
                        <tr>
                            <td>
                                <strong>{{ $challenge->title }}</strong>
                                <small>{{ $challenge->slug }}</small>
                            </td>
                            <td>{{ config('ctf.categories.' . $challenge->category, $challenge->category) }}</td>
                            <td>{{ config('ctf.difficulties.' . $challenge->difficulty, $challenge->difficulty) }}</td>
                            <td class="rank-cell">{{ number_format($challenge->points) }}</td>
                            <td>
                                @if ($challenge->target_type === 'external')
                                    <span class="state-label">External</span>
                                @else
                                    <span class="state-label">Internal</span>
                                @endif
                            </td>
                            <td>
                                @if ($challenge->is_active)
                                    <span class="state-label state-active">Active</span>
                                @else
                                    <span class="state-label">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="member-actions">
                                    <a class="small-action" href="{{ route('admin.challenges.edit', ['challenge' => $challenge]) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.challenges.destroy', ['challenge' => $challenge]) }}" onsubmit="return confirm('Delete this challenge? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="small-action" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
