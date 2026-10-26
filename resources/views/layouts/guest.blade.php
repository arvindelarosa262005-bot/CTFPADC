<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · P4DC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body">
    <main class="auth-layout">
        <section class="auth-aside">
            <a class="brand brand-auth" href="{{ route('login') }}" aria-label="P4DC"><span class="brand-mark">P<span>4</span></span><span class="brand-name">P4DC<span>CYBER RANGE</span></span></a>
            <div class="auth-aside-content">
                <div class="eyebrow"><span class="status-dot"></span> LEARN. TEST. LEVEL UP.</div>
                <h1>Curiosity is<br>your best <em>exploit.</em></h1>
                <p>A hands-on space to build security instincts, solve real-world-inspired problems, and think a little differently.</p>
            </div>
            <div class="auth-aside-foot"><span>INDEPENDENT THINKING. PRACTICAL SECURITY.</span><span>EST. 2026</span></div>
            <div class="auth-grid" aria-hidden="true"></div>
        </section>
        <section class="auth-panel">
            <div class="auth-panel-top"><span>MEMBER ACCESS</span><span class="auth-lock">P4DC / AUTH</span></div>
            <div class="auth-form-wrap">
                @yield('content')
            </div>
            <div class="auth-panel-foot">SECURE SESSION <span class="status-dot"></span></div>
        </section>
    </main>
</body>
</html>
