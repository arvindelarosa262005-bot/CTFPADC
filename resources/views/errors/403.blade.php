@extends('layouts.app')

@section('title', '403 Forbidden')
@section('section', 'ERROR / 403')

@section('content')
<section class="welcome-row">
    <div>
        <div class="eyebrow">RESTRICTED ACCESS <span class="eyebrow-divider">/</span> 403</div>
        <h1>Access forbidden<span class="admin-heading-dot">.</span></h1>
        <p class="form-notice form-notice-error" style="margin-top: 18px;">
            {{ $exception->getMessage() ?: 'You do not have permission to access or perform this action.' }}
        </p>
        <p style="margin-top: 18px;">
            <a href="{{ route('admin.dashboard') }}" class="small-action" style="display: inline-block; text-decoration: none; padding: 6px 14px;">Return to Control Room</a>
        </p>
    </div>
</section>
@endsection

