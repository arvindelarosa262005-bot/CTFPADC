@extends('layouts.app')

@section('title', 'Join a team')
@section('section', 'TEAM ACCESS')

@section('content')
    <section class="page-intro"><div class="eyebrow">INVITATION ONLY <span class="eyebrow-divider">/</span> TEAM ACCESS</div><h1>Join a team</h1><p>Use the invitation code shared by its captain.</p></section>
    <section class="team-panel join-panel">
        @if ($errors->any())<div class="form-notice form-notice-error" role="alert">{{ $errors->first() }}</div>@endif
        <form class="auth-form" method="POST" action="{{ route('teams.join.store') }}">
            @csrf
            <label for="invitation_code">Invitation code</label>
            <input id="invitation_code" name="invitation_code" type="text" inputmode="numeric" placeholder="6-digit code (e.g. 482917)" value="{{ old('invitation_code') }}" minlength="6" maxlength="6" pattern="[0-9]{6}" autocomplete="off" spellcheck="false" required autofocus>
            <button class="button button-primary" type="submit">Join team <span aria-hidden="true">→</span></button>
        </form>
        <a class="text-link team-back-link" href="{{ route('teams.index') }}">Back to team options</a>
    </section>
@endsection
