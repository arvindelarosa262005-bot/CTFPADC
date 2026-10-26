@extends('layouts.app')

@section('title', '404 Not Found')
@section('section', 'ERROR / 404')

@section('content')
<section class="welcome-row">
    <div>
        <div class="eyebrow">RESOURCE NOT FOUND <span class="eyebrow-divider">/</span> 404</div>
        <h1>Target not found<span class="admin-heading-dot">.</span></h1>
        <p class="form-notice form-notice-error" style="margin-top: 18px;">
            {{ $exception->getMessage() ?: 'The requested resource, page, or target could not be found.' }}
        </p>
        <p style="margin-top: 18px;">
            <a href="{{ route('admin.dashboard') }}" class="small-action" style="display: inline-block; text-decoration: none; padding: 6px 14px;">Return to Control Room</a>
        </p>
    </div>
</section>
@endsection

