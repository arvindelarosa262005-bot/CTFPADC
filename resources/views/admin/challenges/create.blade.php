@extends('layouts.app')

@section('title', 'Admin — Create challenge')
@section('section', 'CHALLENGE MANAGEMENT')

@section('content')
    <section class="welcome-row admin-welcome">
        <div>
            <div class="eyebrow">RESTRICTED ACCESS <span class="eyebrow-divider">/</span> NEW CHALLENGE</div>
            <h1>Create challenge<span class="admin-heading-dot">.</span></h1>
        </div>
    </section>

    <form method="POST" action="{{ route('admin.challenges.store') }}" class="admin-challenge-form">
        @csrf
        @include('admin.challenges._form')
        <div class="form-actions">
            <a class="button button-outline" href="{{ route('admin.challenges.index') }}">Cancel</a>
            <button class="button button-primary" type="submit">Create challenge</button>
        </div>
    </form>
@endsection
