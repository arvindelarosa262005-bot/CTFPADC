<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PADC Cyber Range 2026 · BSIT Mamburao Organization</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@500;600;700&family=DM+Mono:wght@400;500&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        :root {
            --padc-purple: #a855f7;
            --padc-purple-bright: #c084fc;
            --padc-purple-dark: #090614;
            --padc-purple-glow: rgba(168, 85, 247, 0.45);
            --padc-gold: #ffb800;
            --padc-gold-dim: rgba(255, 184, 0, 0.15);
            --padc-card-bg: rgba(20, 13, 41, 0.75);
            --padc-border: rgba(168, 85, 247, 0.28);
            --padc-border-hover: rgba(192, 132, 252, 0.65);
        }

        .landing-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px 80px;
        }

        /* Top Header Navigation */
        .landing-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 0;
            border-bottom: 1px solid var(--line);
            margin-bottom: 40px;
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(9, 6, 20, 0.88);
        }
        .landing-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }
        .landing-brand-logo {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.25), rgba(192, 132, 252, 0.1));
            border: 1px solid var(--padc-purple-bright);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--display);
            font-weight: 700;
            font-size: 20px;
            color: var(--padc-purple-bright);
            box-shadow: 0 0 20px rgba(168, 85, 247, 0.35);
        }
        .landing-brand-text h2 {
            margin: 0;
            font-size: 18px;
            letter-spacing: 0.08em;
            color: var(--text-bright);
            line-height: 1.1;
        }
        .landing-brand-text span {
            font-size: 11px;
            font-family: var(--mono);
            color: var(--padc-purple-bright);
            letter-spacing: 0.12em;
        }
        .landing-nav-links {
            display: flex;
            align-items: center;
            gap: 22px;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .landing-nav-links a {
            font-size: 14px;
            font-family: var(--display);
            font-weight: 600;
            color: var(--muted);
            transition: color 0.2s ease;
            letter-spacing: 0.05em;
        }
        .landing-nav-links a:hover {
            color: var(--padc-purple-bright);
        }
        .landing-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Hero Section */
        .landing-hero {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: center;
            margin-bottom: 70px;
            padding: 20px 0 40px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: rgba(168, 85, 247, 0.1);
            border: 1px solid rgba(168, 85, 247, 0.35);
            border-radius: 20px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--padc-purple-bright);
            letter-spacing: 0.08em;
            margin-bottom: 20px;
        }
        .hero-badge .pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--padc-purple-bright);
            border-radius: 50%;
            box-shadow: 0 0 10px var(--padc-purple-bright);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.8); }
        }
        .hero-title {
            font-size: 46px;
            line-height: 1.12;
            margin: 0 0 16px;
            font-weight: 700;
            letter-spacing: -0.01em;
            color: var(--text-bright);
        }
        .hero-title span.highlight {
            background: linear-gradient(90deg, #c084fc, #ffb800);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-family: var(--display);
            font-size: 18px;
            color: var(--padc-gold);
            margin: 0 0 18px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        .hero-desc {
            font-size: 16px;
            line-height: 1.65;
            color: var(--muted);
            margin: 0 0 32px;
            max-width: 620px;
        }
        .hero-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            align-items: center;
        }
        .btn-glow-purple {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 13px 28px;
            background: linear-gradient(135deg, #a855f7, #7e22ce);
            color: #ffffff !important;
            font-weight: 700;
            font-size: 15px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            box-shadow: 0 0 25px rgba(168, 85, 247, 0.45);
            transition: all 0.25s ease;
            text-decoration: none;
            border: 1px solid rgba(216, 180, 254, 0.4);
        }
        .btn-glow-purple:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #c084fc, #9333ea);
            box-shadow: 0 0 35px rgba(168, 85, 247, 0.65);
        }
        .btn-outline-purple {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(168, 85, 247, 0.08);
            border: 1px solid var(--padc-purple-bright);
            color: var(--padc-purple-bright) !important;
            font-weight: 600;
            font-size: 14px;
            border-radius: 6px;
            letter-spacing: 0.05em;
            transition: all 0.25s ease;
            text-decoration: none;
        }
        .btn-outline-purple:hover {
            background: rgba(168, 85, 247, 0.2);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.35);
            transform: translateY(-2px);
        }
        .btn-fb-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: rgba(24, 119, 242, 0.12);
            border: 1px solid #1877f2;
            color: #82b7ff !important;
            font-weight: 600;
            font-size: 14px;
            border-radius: 6px;
            transition: all 0.25s ease;
            text-decoration: none;
        }
        .btn-fb-link:hover {
            background: rgba(24, 119, 242, 0.25);
            box-shadow: 0 0 15px rgba(24, 119, 242, 0.4);
            transform: translateY(-2px);
        }

        /* Hero Visual Frame */
        .hero-visual-frame {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--padc-border);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 0 0 35px rgba(168, 85, 247, 0.2);
            background: var(--panel-raised);
        }
        .hero-visual-frame img {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.5s ease;
        }
        .hero-visual-frame:hover img {
            transform: scale(1.02);
        }
        .hero-frame-hud {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 16px 20px;
            background: linear-gradient(180deg, transparent, rgba(9, 6, 20, 0.95));
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--mono);
            font-size: 12px;
        }

        /* Stats Bar */
        .landing-stats-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 70px;
        }
        .stat-hud-card {
            background: var(--padc-card-bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .stat-hud-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--padc-purple-bright), var(--padc-gold));
        }
        .stat-hud-card:hover {
            border-color: var(--padc-border-hover);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(168, 85, 247, 0.15);
        }
        .stat-number {
            font-size: 34px;
            font-weight: 700;
            color: var(--text-bright);
            line-height: 1;
            font-family: var(--display);
        }
        .stat-label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-family: var(--mono);
        }
        .stat-highlight {
            font-size: 11px;
            color: var(--padc-purple-bright);
        }

        /* Section Headings */
        .section-header {
            margin-bottom: 36px;
            text-align: center;
        }
        .section-header .eyebrow {
            font-family: var(--mono);
            color: var(--padc-purple-bright);
            font-size: 12px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        .section-header h2 {
            font-size: 36px;
            font-weight: 700;
            margin: 0 0 12px;
            color: var(--text-bright);
            letter-spacing: -0.01em;
        }
        .section-header p {
            font-size: 16px;
            color: var(--muted);
            max-width: 680px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Official Victory Posters Showcase */
        .posters-showcase-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-bottom: 50px;
        }
        .poster-card {
            background: var(--padc-card-bg);
            border: 1px solid var(--padc-border);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
        }
        .poster-card:hover {
            border-color: var(--padc-purple-bright);
            transform: translateY(-5px);
            box-shadow: 0 20px 45px rgba(168, 85, 247, 0.25);
        }
        .poster-img-wrap {
            position: relative;
            background: #000;
            overflow: hidden;
        }
        .poster-img-wrap img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
            transition: transform 0.4s ease;
        }
        .poster-card:hover .poster-img-wrap img {
            transform: scale(1.02);
        }
        .poster-body {
            padding: 22px 24px;
            background: rgba(14, 9, 31, 0.95);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .poster-badge {
            display: inline-block;
            align-self: flex-start;
            padding: 4px 10px;
            background: rgba(255, 184, 0, 0.15);
            border: 1px solid var(--padc-gold);
            border-radius: 4px;
            color: var(--padc-gold);
            font-family: var(--mono);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .poster-body h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-bright);
        }
        .poster-body p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: var(--muted);
        }

        /* Individual Champions Triad */
        .individual-trio-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 70px;
        }
        .individual-card {
            background: var(--padc-card-bg);
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }
        .individual-card:hover {
            border-color: var(--padc-purple-bright);
            transform: translateY(-4px);
            box-shadow: 0 16px 36px rgba(168, 85, 247, 0.2);
        }
        .individual-card img {
            width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }
        .individual-body {
            padding: 18px 20px;
            background: rgba(14, 9, 31, 0.92);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .individual-rank-tag {
            font-family: var(--mono);
            font-size: 11px;
            font-weight: 700;
            color: var(--padc-purple-bright);
            letter-spacing: 0.08em;
        }
        .individual-body h4 {
            margin: 0;
            font-size: 17px;
            color: var(--text-bright);
            font-weight: 700;
        }
        .individual-body span {
            font-size: 12px;
            color: var(--muted);
        }

        /* Arena Challenge Domains */
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 70px;
        }
        .category-card {
            background: var(--padc-card-bg);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .category-card:hover {
            border-color: var(--padc-purple-bright);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(168, 85, 247, 0.15);
        }
        .category-icon {
            font-size: 28px;
            margin-bottom: 14px;
            display: inline-block;
        }
        .category-card h4 {
            margin: 0 0 10px;
            font-size: 18px;
            color: var(--text-bright);
        }
        .category-card p {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
            color: var(--muted);
        }

        /* Clan Facebook Connect Callout */
        .clan-connect-card {
            background: linear-gradient(135deg, rgba(32, 17, 66, 0.7), rgba(14, 9, 31, 0.9));
            border: 1px solid var(--padc-border);
            border-radius: 14px;
            padding: 40px;
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
        }
        .clan-connect-card h2 {
            font-size: 32px;
            margin: 0 0 16px;
            color: var(--text-bright);
        }
        .clan-connect-card p {
            font-size: 16px;
            max-width: 680px;
            margin: 0 auto 28px;
            color: var(--muted);
            line-height: 1.65;
        }
        .connect-links {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        /* Footer */
        .landing-footer {
            border-top: 1px solid var(--line);
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            font-family: var(--mono);
            font-size: 12px;
            color: var(--faint);
        }
        .footer-brand {
            color: var(--muted);
        }

        /* Responsive Breakpoints */
        @media (max-width: 980px) {
            .landing-hero {
                grid-template-columns: 1fr;
                gap: 36px;
            }
            .posters-showcase-grid {
                grid-template-columns: 1fr;
            }
            .individual-trio-grid {
                grid-template-columns: 1fr;
            }
            .landing-stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            .categories-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .hero-title {
                font-size: 34px;
            }
            .landing-stats-bar {
                grid-template-columns: 1fr;
            }
            .landing-nav-links {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="landing-wrap">
        <!-- Top Tactical Navbar -->
        <nav class="landing-nav" aria-label="Main Navigation">
            <a class="landing-brand" href="{{ route('home') }}">
                <div class="landing-brand-logo">P4</div>
                <div class="landing-brand-text">
                    <h2>PADC CYBER RANGE</h2>
                    <span>BSIT MAMBURAO · OMSC</span>
                </div>
            </a>

            <ul class="landing-nav-links">
                <li><a href="#about">About Clan</a></li>
                <li><a href="#hall-of-fame">Hall of Fame</a></li>
                <li><a href="#teams">Teams</a></li>
                <li><a href="#arena">Cyber Arena</a></li>
                <li><a href="https://www.facebook.com/padclan" target="_blank" rel="noopener noreferrer">FB Page &nearr;</a></li>
            </ul>

            <div class="landing-nav-actions">
                @auth
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}" class="btn-glow-purple" style="padding: 9px 18px; font-size: 13px;">Admin Console &rarr;</a>
                    @else
                        <a href="{{ route('challenges') }}" class="btn-glow-purple" style="padding: 9px 18px; font-size: 13px;">Enter Arena &rarr;</a>
                        <a href="{{ route('dashboard') }}" class="btn-outline-purple" style="padding: 8px 16px; font-size: 13px;">Dashboard</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="btn-outline-purple" style="padding: 8px 16px; font-size: 13px;">Sign In</a>
                    <a href="{{ route('register') }}" class="btn-glow-purple" style="padding: 9px 18px; font-size: 13px;">Register</a>
                @endauth
            </div>
        </nav>

        <!-- Logged In Operative Banner -->
        @auth
            <div style="margin-bottom: 30px; padding: 14px 20px; background: rgba(168, 85, 247, 0.1); border: 1px solid var(--padc-border); border-radius: 8px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px; font-family: var(--mono); font-size: 13px;">
                    <span style="display: inline-block; width: 10px; height: 10px; background: var(--padc-purple-bright); border-radius: 50%; box-shadow: 0 0 8px var(--padc-purple-bright);"></span>
                    <span>AUTHENTICATED OPERATIVE: <strong style="color: #fff;">{{ auth()->user()->callsign }}</strong> [Role: {{ strtoupper(auth()->user()->role) }}]</span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="{{ route('challenges') }}" style="color: var(--padc-purple-bright); font-weight: bold; font-size: 13px; text-decoration: underline;">Launch Challenge Arena &rarr;</a>
                    <span style="color: var(--muted);">&middot;</span>
                    <a href="{{ route('dashboard') }}" style="color: var(--padc-gold); font-size: 13px; text-decoration: underline;">My Mission Dashboard</a>
                </div>
            </div>
        @endauth

        <!-- Hero Section -->
        <section class="landing-hero" id="about">
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="pulse-dot"></span>
                    <span>PADC CYBER DEFENSE INITIATIVE · PURPLE CLAN</span>
                </div>
                <h1 class="hero-title">
                    Programmers', Animators', &amp; <span class="highlight">Developers' Clan</span>
                </h1>
                <div class="hero-subtitle">
                    Bachelor of Science in Information Technology · OMSC Mamburao Campus
                </div>
                <p class="hero-desc">
                    The premier student technology organization of Occidental Mindoro State University – Mamburao Campus. 
                    Empowering future IT leaders, ethical hackers, and software innovators through hands-on cyber defense, 
                    full-stack engineering, and regional competitions.
                </p>
                <div class="hero-buttons">
                    @auth
                        <a href="{{ route('challenges') }}" class="btn-glow-purple">
                            <span>🎯 Launch Cyber Arena</span>
                        </a>
                        <a href="{{ route('dashboard') }}" class="btn-outline-purple">
                            <span>📊 My Mission Dashboard</span>
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="btn-glow-purple">
                            <span>⚡ Register as Operative</span>
                        </a>
                        <a href="{{ route('login') }}" class="btn-outline-purple">
                            <span>🔑 Operative Sign In</span>
                        </a>
                    @endauth
                    <a href="https://www.facebook.com/padclan" target="_blank" rel="noopener noreferrer" class="btn-fb-link">
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        <span>Official FB Page</span>
                    </a>
                </div>
            </div>

            <div class="hero-visual-frame">
                <img src="{{ asset('images/landing/padc_hero_cyber_range.jpg') }}" alt="PADC Cyber Defense Command Arena">
                <div class="hero-frame-hud">
                    <span style="color: var(--padc-purple-bright);">● PURPLE ARENA // LIVE DEFENSE</span>
                    <span style="color: var(--padc-gold);">P4DC-2026 TACTICAL</span>
                </div>
            </div>
        </section>

        <!-- Stats Bar -->
        <section class="landing-stats-bar">
            <div class="stat-hud-card">
                <span class="stat-number" style="color: var(--padc-purple-bright);">12</span>
                <span class="stat-label">Active Scenarios</span>
                <span class="stat-highlight">Web, Crypto, Forensics &amp; Reversing</span>
            </div>
            <div class="stat-hud-card">
                <span class="stat-number" style="color: var(--padc-gold);">6th &amp; 7th</span>
                <span class="stat-label">MIMAROPA Champions</span>
                <span class="stat-highlight">Teams CIPHERA &amp; 404 Not Defeated</span>
            </div>
            <div class="stat-hud-card">
                <span class="stat-number" style="color: var(--padc-purple-bright);">Top 10</span>
                <span class="stat-label">Individual Elite</span>
                <span class="stat-highlight">Regional Top Individual Performers</span>
            </div>
            <div class="stat-hud-card">
                <span class="stat-number" style="color: var(--padc-gold);">100%</span>
                <span class="stat-label">AI Anti-Cheat Radar</span>
                <span class="stat-highlight">Honeypot Canaries &amp; Live Telemetry</span>
            </div>
        </section>

        <!-- Official HackForGov Victory Showcase (User Uploaded Posters) -->
        <section id="hall-of-fame" style="margin-bottom: 70px;">
            <div class="section-header">
                <div class="eyebrow">DICT Regional Cyber Challenge // MIMAROPA</div>
                <h2>HackForGov Hall of Fame</h2>
                <p>
                    Celebrating the official achievements and victory milestones of PADC BSIT Mamburao Organization 
                    at the HackForGov Regional Cyber Competitions.
                </p>
            </div>

            <!-- Two Team Posters: CIPHERA (6th Place) & 404 NOT DEFEATED (7th Place) -->
            <div class="posters-showcase-grid" id="teams">
                <!-- Team CIPHERA -->
                <div class="poster-card">
                    <div class="poster-img-wrap">
                        <img src="{{ asset('images/landing/team_ciphera_6th.jpg') }}" alt="Team CIPHERA - 6th Place HackForGov MIMAROPA">
                    </div>
                    <div class="poster-body">
                        <span class="poster-badge">🏆 6TH PLACE REGIONAL CHAMPIONS</span>
                        <h3>TEAM CIPHERA</h3>
                        <p>
                            Representing Occidental Mindoro State University – Mamburao Campus BSIT at the HackForGov MIMAROPA 
                            Cyber Challenge. Showcased exceptional prowess in cryptographic analysis, network packet triage, 
                            and digital forensics.
                        </p>
                        <div style="font-family: var(--mono); font-size: 12px; color: var(--padc-purple-bright); padding-top: 10px; border-top: 1px solid var(--line);">
                            <strong>OPERATIVES:</strong> ITOSHI &middot; FAE &middot; VOXEL &middot; ZHEROSS
                            <br>
                            <span style="color: var(--muted);">COACH: Mr. Roland Roy A. Camus</span>
                        </div>
                    </div>
                </div>

                <!-- Team 404 NOT DEFEATED -->
                <div class="poster-card">
                    <div class="poster-img-wrap">
                        <img src="{{ asset('images/landing/team_404_not_defeated_7th.jpg') }}" alt="Team 404 NOT DEFEATED - 7th Place HackForGov MIMAROPA">
                    </div>
                    <div class="poster-body">
                        <span class="poster-badge">🏆 7TH PLACE REGIONAL FINALIST</span>
                        <h3>TEAM 404 NOT DEFEATED</h3>
                        <p>
                            Representing Occidental Mindoro State University – Mamburao Campus BSIT at the HackForGov MIMAROPA 
                            Cyber Challenge. Demonstrated tactical problem solving, incident triage, and exploit defense 
                            against elite regional universities.
                        </p>
                        <div style="font-family: var(--mono); font-size: 12px; color: var(--padc-purple-bright); padding-top: 10px; border-top: 1px solid var(--line);">
                            <strong>OPERATIVES:</strong> DAZZY &middot; FINN &middot; JYNX &middot; CLOUD
                            <br>
                            <span style="color: var(--muted);">COACH: Ms. Erika Jane D. Tadeja</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Individual Top Performers Triad (Arvin, Hannah, Rhoss) -->
            <div style="margin-bottom: 24px; text-align: center;">
                <h3 style="font-size: 24px; color: var(--text-bright); margin-bottom: 8px;">Top Individual Regional Scorers</h3>
                <p style="color: var(--muted); font-size: 14px; margin: 0;">Recognizing the individual excellence of our PADC BSIT operatives across MIMAROPA.</p>
            </div>

            <div class="individual-trio-grid">
                <!-- Hannah C. Alfaro (Top 9) -->
                <div class="individual-card">
                    <img src="{{ asset('images/landing/hannah_alfaro_top9.jpg') }}" alt="Hannah C. Alfaro - Top 9">
                    <div class="individual-body">
                        <span class="individual-rank-tag">🏅 INDIVIDUAL SCORE: TOP 9</span>
                        <h4>ALFARO, HANNAH C.</h4>
                        <span>Team 404 Not Defeated &middot; Coach: Ms. Erika Jane D. Tadeja</span>
                        <span style="font-family: var(--mono); font-size: 11px; color: var(--padc-gold);">HackForGov MIMAROPA 2024</span>
                    </div>
                </div>

                <!-- Arvin M. Dela Rosa (Top 10) -->
                <div class="individual-card" style="border-color: var(--padc-purple-bright);">
                    <img src="{{ asset('images/landing/arvin_dela_rosa_top10.jpg') }}" alt="Arvin M. Dela Rosa - Top 10">
                    <div class="individual-body">
                        <span class="individual-rank-tag" style="color: var(--padc-gold);">🏅 INDIVIDUAL SCORE: TOP 10</span>
                        <h4>DELA ROSA, ARVIN M.</h4>
                        <span>Team 404 Not Defeated &middot; Coach: Ms. Erika Jane D. Tadeja</span>
                        <span style="font-family: var(--mono); font-size: 11px; color: var(--padc-gold);">HackForGov MIMAROPA 2024</span>
                    </div>
                </div>

                <!-- Rhoss Dhenver M. Pineda, Jr. (Top 11) -->
                <div class="individual-card">
                    <img src="{{ asset('images/landing/rhoss_pineda_top11.jpg') }}" alt="Rhoss Dhenver M. Pineda, Jr. - Top 11">
                    <div class="individual-body">
                        <span class="individual-rank-tag">🏅 INDIVIDUAL SCORE: TOP 11</span>
                        <h4>PINEDA, RHOSS DHENVER M., JR.</h4>
                        <span>Team CIPHERA &middot; Coach: Mr. Roland Roy A. Camus</span>
                        <span style="font-family: var(--mono); font-size: 11px; color: var(--padc-gold);">HackForGov MIMAROPA 2024</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Arena Challenge Domains -->
        <section id="arena" style="margin-bottom: 70px;">
            <div class="section-header">
                <div class="eyebrow">Battleground Architecture</div>
                <h2>Explore Cyber Range Domains</h2>
                <p>
                    Real-world tactical challenges developed to build practical instincts and offensive-defensive skillsets.
                </p>
            </div>

            <div class="categories-grid">
                <div class="category-card">
                    <span class="category-icon">🌐</span>
                    <h4>Web Exploitation</h4>
                    <p>
                        Inspect developer footprints, analyze dynamic database query vulnerabilities (SQLi auth bypass), 
                        and probe hidden administrative web portals.
                    </p>
                </div>
                <div class="category-card">
                    <span class="category-icon">🔐</span>
                    <h4>Cryptography</h4>
                    <p>
                        Break classical substitution ciphers (Caesar / ROT13), crack password hashes with dictionary attacks, 
                        and exploit malleability in RSA encryption schemes.
                    </p>
                </div>
                <div class="category-card">
                    <span class="category-icon">🕵️</span>
                    <h4>Digital Forensics</h4>
                    <p>
                        Extract exfiltrated data from volatile memory dumps (.bin), analyze packet captures (PCAP) in Wireshark, 
                        and parse security incident timeline logs.
                    </p>
                </div>
                <div class="category-card">
                    <span class="category-icon">🧩</span>
                    <h4>Steganography</h4>
                    <p>
                        Uncover covert bitstreams hidden in least-significant data bits (LSB), analyze pixel spacing, 
                        and decode zero-width ASCII transmissions.
                    </p>
                </div>
                <div class="category-card">
                    <span class="category-icon">⚙️</span>
                    <h4>Reverse Engineering</h4>
                    <p>
                        Decompile mystery binaries, reverse-engineer proprietary transformation algorithms, 
                        and pass interactive verification oracle challenges.
                    </p>
                </div>
                <div class="category-card">
                    <span class="category-icon">🤖</span>
                    <h4>AI Anti-Cheat Radar</h4>
                    <p>
                        Equipped with invisible LLM honeypot canaries, keystroke cadence monitoring, and tab switch telemetry 
                        to ensure fair, skill-first competition.
                    </p>
                </div>
            </div>

            <div style="text-align: center;">
                @auth
                    <a href="{{ route('challenges') }}" class="btn-glow-purple" style="padding: 16px 36px; font-size: 16px;">
                        <span>🎯 ENTER ARENA &amp; SOLVE CHALLENGES &rarr;</span>
                    </a>
                @else
                    <a href="{{ route('register') }}" class="btn-glow-purple" style="padding: 16px 36px; font-size: 16px;">
                        <span>⚡ CREATE OPERATIVE ACCOUNT &rarr;</span>
                    </a>
                @endauth
            </div>
        </section>

        <!-- Clan Facebook Connect Callout -->
        <section class="clan-connect-card">
            <h2>Connect with PADC on Facebook</h2>
            <p>
                Stay updated on upcoming BSIT hackathons, academic tutorials, competition announcements, 
                and student organization milestones. Follow the official clan page today!
            </p>
            <div class="connect-links">
                <a href="https://www.facebook.com/padclan" target="_blank" rel="noopener noreferrer" class="btn-fb-link" style="padding: 14px 28px; font-size: 15px;">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <span>facebook.com/padclan &nearr;</span>
                </a>
                <a href="mailto:padc.omscmbo@gmail.com" class="btn-outline-purple" style="padding: 14px 24px; font-size: 15px;">
                    <span>📧 padc.omscmbo@gmail.com</span>
                </a>
                <a href="https://instagram.com/_padclan" target="_blank" rel="noopener noreferrer" class="btn-outline-purple" style="padding: 14px 24px; font-size: 15px;">
                    <span>📸 Instagram: @_padclan &nearr;</span>
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="landing-footer">
            <div class="footer-brand">
                <strong>PADC - BSIT Mamburao Organization</strong> · Occidental Mindoro State University (OMSC)
                <br>
                Programmers', Animators', and Developers' Clan &copy; {{ date('Y') }}. All rights reserved.
            </div>
            <div>
                <span>P4DC PURPLE CYBER RANGE · EST. 2026</span>
            </div>
        </footer>
    </div>
</body>
</html>
