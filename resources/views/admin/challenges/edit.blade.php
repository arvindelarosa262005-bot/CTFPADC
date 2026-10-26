@extends('layouts.app')

@section('title', 'Admin — Edit challenge')
@section('section', 'CHALLENGE MANAGEMENT')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif

    <section class="welcome-row admin-welcome">
        <div>
            <div class="eyebrow">RESTRICTED ACCESS <span class="eyebrow-divider">/</span> EDIT CHALLENGE</div>
            <h1>Edit challenge<span class="admin-heading-dot">.</span></h1>
            <p class="edit-context">Editing <strong>{{ $challenge->title }}</strong></p>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.challenges.update', ['challenge' => $challenge]) }}" class="admin-challenge-form">
        @csrf
        @method('PATCH')
        @include('admin.challenges._form')
        <div class="form-actions">
            <a class="button button-outline" href="{{ route('admin.challenges.index') }}">Cancel</a>
            <button class="button button-primary" type="submit">Save changes</button>
        </div>
    </form>
@endsection
