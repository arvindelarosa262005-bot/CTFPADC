@extends('layouts.app')

@section('title', 'Admin — Challenges')
@section('section', 'CHALLENGE MANAGEMENT')

@section('content')
    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="form-notice form-notice-error" role="alert">{{ $errors->first() }}</p>
    @endif

    <section class="welcome-row admin-welcome" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 24px;">
        <div>
            <div class="eyebrow"><span class="terminal-prompt">SYS_ADMIN</span> <span class="eyebrow-divider">/</span> CTF RANGE CONTROLLER</div>
            <h1 style="margin: 4px 0 0; font-size: 26px; font-weight: 700; color: #fff;">Challenge Release Control<span class="admin-heading-dot" style="color: var(--neon-cyan);">.</span></h1>
            <p style="margin: 6px 0 0; font-size: 13px; color: var(--muted); max-width: 600px;">
                Ikaw lang ang may kontrol kung anong challenges ang bubuksan sa mga player. Ang mga naka-markang <strong style="color: #00ff9d;">LIVE</strong> lamang ang makikita sa Contestant Range.
            </p>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <form method="POST" action="{{ route('admin.challenges.bulk_release') }}" onsubmit="return confirm('Sigurado ka bang ilalabas ang LAHAT ng challenges sa mga contestant?');">
                @csrf
                <button class="button button-outline" type="submit" style="border-color: rgba(0, 255, 157, 0.4); color: #00ff9d; font-size: 12px; padding: 8px 14px;">
                    🚀 Release All to Players
                </button>
            </form>
            <form method="POST" action="{{ route('admin.challenges.bulk_hide') }}" onsubmit="return confirm('Sigurado ka bang itatago ang LAHAT ng challenges sa mga contestant?');">
                @csrf
                <button class="button button-outline" type="submit" style="border-color: rgba(255, 184, 108, 0.4); color: #ffb86c; font-size: 12px; padding: 8px 14px;">
                    🔒 Hide All (Staging)
                </button>
            </form>
            <a class="button button-outline" href="{{ route('admin.challenges.create') }}" style="border-color: var(--neon-cyan); color: var(--neon-cyan); font-size: 12px; padding: 8px 14px;">
                + Create Challenge
            </a>
        </div>
    </section>

    <!-- Telemetry Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: rgba(13, 20, 36, 0.8); border: 1px solid var(--line); border-radius: 8px; padding: 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-family: var(--mono); font-size: 11px; color: var(--muted); letter-spacing: 0.08em;">TOTAL CHALLENGES</div>
                <div style="font-size: 24px; font-weight: 700; color: #fff; margin-top: 4px;">{{ $challenges->count() }}</div>
            </div>
            <div style="font-size: 28px; opacity: 0.6;">📦</div>
        </div>
        <div style="background: rgba(0, 255, 157, 0.05); border: 1px solid rgba(0, 255, 157, 0.3); border-radius: 8px; padding: 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-family: var(--mono); font-size: 11px; color: #00ff9d; letter-spacing: 0.08em;">🟢 LIVE TO PLAYERS</div>
                <div style="font-size: 24px; font-weight: 700; color: #00ff9d; margin-top: 4px;">{{ $activeCount }}</div>
            </div>
            <div style="font-size: 28px; opacity: 0.8;">👁️</div>
        </div>
        <div style="background: rgba(255, 184, 108, 0.05); border: 1px solid rgba(255, 184, 108, 0.3); border-radius: 8px; padding: 16px; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <div style="font-family: var(--mono); font-size: 11px; color: #ffb86c; letter-spacing: 0.08em;">🔒 HIDDEN (STAGED)</div>
                <div style="font-size: 24px; font-weight: 700; color: #ffb86c; margin-top: 4px;">{{ $hiddenCount }}</div>
            </div>
            <div style="font-size: 28px; opacity: 0.8;">🛡️</div>
        </div>
    </div>

    @if ($challenges->isEmpty())
        <section class="empty-state admin-empty">
            <span class="empty-symbol">∅</span>
            <div>
                <h3>No challenges configured</h3>
                <p>Create the first challenge to publish it to the range.</p>
            </div>
        </section>
    @else
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>CHALLENGE</th>
                        <th>CATEGORY</th>
                        <th>DIFFICULTY</th>
                        <th>POINTS</th>
                        <th>TARGET / FILE</th>
                        <th style="min-width: 220px;">CONTESTANT VISIBILITY</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($challenges as $challenge)
                        <tr style="{{ ! $challenge->is_active ? 'opacity: 0.75;' : '' }}">
                            <td>
                                <strong>{{ $challenge->title }}</strong>
                                <small style="display: block; font-family: var(--mono); color: var(--faint);">{{ $challenge->slug }}</small>
                            </td>
                            <td>{{ config('ctf.categories.' . $challenge->category, $challenge->category) }}</td>
                            <td>
                                <span class="state-label" style="text-transform: capitalize;">
                                    {{ config('ctf.difficulties.' . $challenge->difficulty, $challenge->difficulty) }}
                                </span>
                            </td>
                            <td class="rank-cell" style="font-weight: 700; color: var(--neon-cyan);">{{ number_format($challenge->points) }}</td>
                            <td>
                                @if ($challenge->hasAttachment())
                                    <span class="state-label" style="color: var(--neon-cyan); border-color: rgba(0, 240, 255, 0.3);">📎 Attachment</span>
                                @elseif ($challenge->target_type === 'external')
                                    <span class="state-label">External Web</span>
                                @else
                                    <span class="state-label">Internal Portal</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    @if ($challenge->is_active)
                                        <span class="state-label" style="background: rgba(0, 255, 157, 0.12); color: #00ff9d; border: 1px solid rgba(0, 255, 157, 0.4); font-weight: 600; padding: 4px 8px;">
                                            🟢 LIVE
                                        </span>
                                        <form method="POST" action="{{ route('admin.challenges.toggle', ['challenge' => $challenge]) }}">
                                            @csrf
                                            <button class="small-action" type="submit" style="color: #ffb86c; border-color: rgba(255, 184, 108, 0.4); padding: 3px 8px; font-size: 11px;" title="Hide this challenge from players">
                                                🔒 Hide
                                            </button>
                                        </form>
                                    @else
                                        <span class="state-label" style="background: rgba(255, 184, 108, 0.08); color: #ffb86c; border: 1px solid rgba(255, 184, 108, 0.3); padding: 4px 8px;">
                                            🔒 HIDDEN
                                        </span>
                                        <form method="POST" action="{{ route('admin.challenges.toggle', ['challenge' => $challenge]) }}">
                                            @csrf
                                            <button class="small-action" type="submit" style="color: #00ff9d; border-color: rgba(0, 255, 157, 0.5); background: rgba(0, 255, 157, 0.06); font-weight: 600; padding: 3px 10px; font-size: 11px;" title="Release this challenge so contestants can play it now">
                                                🚀 Release to Players
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="member-actions">
                                    <a class="small-action" href="{{ route('admin.challenges.edit', ['challenge' => $challenge]) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.challenges.destroy', ['challenge' => $challenge]) }}" onsubmit="return confirm('Delete this challenge? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="small-action" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
