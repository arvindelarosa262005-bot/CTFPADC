@extends('layouts.app')

@section('title', 'My team')
@section('section', 'TEAM ACCESS')

@section('content')
    @if (session('status'))<p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>@endif
    <section class="page-intro"><div class="eyebrow">TEAM SPACE <span class="eyebrow-divider">/</span> OPTIONAL MEMBERSHIP</div><h1>Find your crew</h1><p>Team membership is optional. Every participant keeps an individual account and individual solve history.</p></section>
    <div class="team-entry-grid">
        <section class="team-panel">
            <div class="eyebrow">START A TEAM</div>
            <h2>Create a team</h2>
            <p>You’ll become its captain and can share a private invitation code with other contestants.</p>
            <form class="team-create-form team-entry-form" method="POST" action="{{ route('teams.store') }}">
                @csrf
                <label for="team-name">Team name</label>
                <input id="team-name" name="name" maxlength="60" minlength="2" required value="{{ old('name') }}" placeholder="Choose a team name">
                @error('name')<span class="field-error">{{ $message }}</span>@enderror
                <button class="button button-primary" type="submit">Create team <span aria-hidden="true">→</span></button>
            </form>
        </section>
        <section class="team-panel">
            <div class="eyebrow">JOIN A TEAM</div>
            <h2>Have an invite?</h2>
            <p>Enter the invitation code shared by a team captain. Invalid codes don’t reveal team details.</p>
            <a class="button button-outline" href="{{ route('teams.join') }}">Enter invitation code <span aria-hidden="true">→</span></a>
        </section>
    </div>
@endsection
