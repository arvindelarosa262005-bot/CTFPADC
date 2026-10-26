<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · P4DC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</head>
<body class="app-body">
    <div class="app-frame">
        <aside class="sidebar" id="primary-navigation">
            <a class="brand" href="{{ route('dashboard') }}" aria-label="P4DC home">
                <span class="brand-mark">P<span>4</span></span>
                <span class="brand-name">P4DC<span>CYBER RANGE</span></span>
            </a>
            <div class="sidebar-label">{{ auth()->user()?->isAdmin() ? 'Administration' : 'Workspace' }}</div>
            <nav class="main-nav" aria-label="Main navigation">
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" @class(['nav-link', 'is-active' => request()->routeIs('admin.dashboard')])><span class="nav-index">01</span>Admin dashboard</a>
                    <a href="{{ route('admin.challenges.index') }}" @class(['nav-link', 'is-active' => request()->routeIs('admin.challenges.*')])><span class="nav-index">02</span>Challenges</a>
                @else
                    <a href="{{ route('dashboard') }}" @class(['nav-link', 'is-active' => request()->routeIs('dashboard')])><span class="nav-index">01</span>Home</a>
                    <a href="{{ route('challenges') }}" @class(['nav-link', 'is-active' => request()->routeIs('challenges', 'challenges.show')])><span class="nav-index">02</span>Challenges</a>
                    <a href="{{ route('teams.index') }}" @class(['nav-link', 'is-active' => request()->routeIs('teams.*')])><span class="nav-index">03</span>My team</a>
                @endif
                <a href="{{ route('profile') }}" @class(['nav-link', 'is-active' => request()->routeIs('profile')])><span class="nav-index">{{ auth()->user()?->isAdmin() ? '03' : '04' }}</span>Profile</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="range-status"><span class="status-dot"></span><span>RANGE STATUS</span><strong>ONLINE</strong></div>
                @if (auth()->check())
                    <div class="sidebar-user">
                        <span class="avatar">{{ strtoupper(substr(auth()->user()->username, 0, 1)) }}</span>
                        <span class="sidebar-user-copy"><strong>{{ auth()->user()->username }}</strong><small>{{ auth()->user()->isAdmin() ? 'Administrator' : 'Contestant' }}</small></span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="icon-action" type="submit" aria-label="Log out" title="Log out">↗</button>
                        </form>
                    </div>
                @endif
            </div>
        </aside>

        <div class="app-main">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" aria-label="Open navigation"><span></span><span></span></button>
                <div class="breadcrumb"><span>OPERATIONS</span><i>/</i>@yield('section', 'OVERVIEW')</div>
                <div class="topbar-actions">
                    <span class="environment-tag"><span class="status-dot"></span>LIVE ENVIRONMENT</span>
                    @if (auth()->check())
                        <details class="profile-menu">
                            <summary><span class="avatar avatar-small">{{ strtoupper(substr(auth()->user()->username, 0, 1)) }}</span><span>{{ auth()->user()->username }}</span><span class="chevron">⌄</span></summary>
                            <div class="profile-popover">
                                <a href="{{ route('profile') }}">View profile</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit">Sign out</button>
                                </form>
                            </div>
                        </details>
                    @else
                        <a href="{{ route('login') }}" class="button button-outline" style="min-height: 32px; padding: 0 12px; font-size: 11px;">Sign in</a>
                    @endif
                </div>
            </header>
            <main class="page-content">
                @yield('content')
            </main>
            @stack('modals')
            <footer class="app-footer"><span>P4DC · LEARN BY BREAKING THINGS RESPONSIBLY</span><span>FLAG FORMAT <b>P4DC{...}</b></span></footer>
        </div>
    </div>
</body>
</html>
