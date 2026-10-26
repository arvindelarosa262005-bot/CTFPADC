@extends('layouts.app')

@section('title', '422 Unprocessable')
@section('section', 'ERROR / 422')

@section('content')
<section class="welcome-row">
    <div>
        <div class="eyebrow">ACTION BLOCKED <span class="eyebrow-divider">/</span> 422</div>
        <h1>Action could not be processed<span class="admin-heading-dot">.</span></h1>
        <p class="form-notice form-notice-error" style="margin-top: 18px;">
            {{ $exception->getMessage() ?: 'The requested operation could not be processed due to existing constraints or active dependencies.' }}
        </p>
        <p style="margin-top: 18px;">
            <a href="{{ route('admin.dashboard') }}" class="small-action" style="display: inline-block; text-decoration: none; padding: 6px 14px;">Return to Control Room</a>
        </p>
    </div>
</section>
@endsection

