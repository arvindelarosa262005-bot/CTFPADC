@extends('layouts.guest')

@section('title', 'Create account')

@section('content')
    <div class="form-heading">
        <div class="eyebrow">NEW OPERATIVE</div>
        <h2>Create your account</h2>
        <p>Get your place in the range.</p>
    </div>
    @if (session('status'))
        <p class="form-notice" role="status">{{ session('status') }}</p>
    @endif
    <form class="auth-form" method="POST" action="{{ route('register.store') }}" novalidate>
        @csrf
        <label for="username">Username</label>
        <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.|-]{3,30}" title="Use 3–30 letters, numbers, periods, underscores, hyphens, or vertical bars." required autofocus>
        @error('username')<p class="field-error">{{ $message }}</p>@enderror

        <label for="nickname">Hacker Callsign / Nickname <small style="color: var(--faint); font-weight: 400;">(Optional, e.g. krypt, cyber)</small></label>
        <input id="nickname" name="nickname" type="text" value="{{ old('nickname') }}" placeholder="e.g. krypt, cyber, spectre" maxlength="50">
        @error('nickname')<p class="field-error">{{ $message }}</p>@enderror

        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
        @error('email')<p class="field-error">{{ $message }}</p>@enderror

        <div class="form-row">
            <div>
                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                @error('password')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </div>
        </div>
        <button class="button button-primary" type="submit">Create account <span aria-hidden="true">→</span></button>
    </form>

    <div class="auth-divider"><span>OR</span></div>

    <a href="{{ route('auth.google') }}" class="button-google" id="google-auth-button">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
        </svg>
        <span>Sign up with Google</span>
    </a>

    <p class="auth-switch">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
