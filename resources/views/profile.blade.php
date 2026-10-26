@extends('layouts.app')

@section('title', 'Profile')
@section('section', 'PROFILE')

@section('content')
    <section class="page-intro"><div class="eyebrow">OPERATIVE FILE <span class="eyebrow-divider">/</span> 04</div><h1>Your profile</h1><p>Account details for this range.</p></section>
    <section class="profile-sheet"><div class="profile-monogram">{{ strtoupper(substr(auth()->user()->username, 0, 1)) }}</div><dl><div><dt>USERNAME</dt><dd>{{ auth()->user()->username }}</dd></div><div><dt>EMAIL</dt><dd>{{ auth()->user()->email }}</dd></div><div><dt>MEMBER SINCE</dt><dd>{{ auth()->user()->created_at->format('M Y') }}</dd></div><div><dt>ACTIVE TEAM</dt><dd>@if (auth()->user()->isAdmin())Administrator account @else @forelse (auth()->user()->activeTeams as $team)<a class="text-link" href="{{ route('teams.show', $team) }}">{{ $team->name }}</a>@empty<a class="text-link" href="{{ route('teams.index') }}">Optional · find or create a team</a>@endforelse @endif</dd></div><div><dt>PERSONAL VERIFIED POINTS</dt><dd>{{ number_format(auth()->user()->verifiedSolves()->sum('points_awarded')) }}</dd></div></dl></section>
@endsection
