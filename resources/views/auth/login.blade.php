@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="form-heading">
        <div class="eyebrow">RETURNING OPERATIVE</div>
        <h2>Welcome back.</h2>
        <p>Pick up where your curiosity left off.</p>
    </div>
    @if (session('status'))
        <p class="form-notice" role="status">{{ session('status') }}</p>
    @endif
    <form class="auth-form" method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="credential">Email or username</label>
        <input id="credential" name="credential" type="text" value="{{ old('credential') }}" autocomplete="username" required autofocus>
        @error('credential')<p class="field-error">{{ $message }}</p>@enderror

        <div class="label-line"><label for="password">Password</label></div>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password')<p class="field-error">{{ $message }}</p>@enderror
        <button class="button button-primary" type="submit">Sign in <span aria-hidden="true">→</span></button>
    </form>

    <div class="auth-divider"><span>OR</span></div>

    <a href="{{ route('auth.google') }}" class="button-google" id="google-auth-button">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
        </svg>
        <span>Continue with Google</span>
    </a>

    <p class="auth-switch">New to the range? <a href="{{ route('register') }}">Create an account</a></p>
@endsection
