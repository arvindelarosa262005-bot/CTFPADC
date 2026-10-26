@php
    $category = config('ctf.categories.' . $challenge->category, $challenge->category);
    $difficulty = config('ctf.difficulties.' . $challenge->difficulty, $challenge->difficulty);
@endphp
<div class="challenge-detail-modal" data-challenge-slug="{{ $challenge->slug }}" data-challenge-id="{{ $challenge->id }}">
    <div class="challenge-modal-header">
        <div class="challenge-header-top">
            <h2 class="challenge-modal-title" id="challenge-modal-title">{{ $challenge->title }}</h2>
            <button type="button" class="challenge-close-btn" data-modal-close aria-label="Close challenge">&times;</button>
        </div>
        <div class="challenge-modal-badges">
            <span class="modal-badge modal-badge-category">{{ $category }}</span>
            <span class="modal-badge modal-badge-difficulty">{{ $difficulty }}</span>
        </div>
        <div class="challenge-modal-meta">
            <span>{{ number_format($challenge->points) }} points</span>
            <span>&middot;</span>
            <span data-modal-status>{{ $isSolved ? 'Solved' : 'Unsolved' }}</span>
        </div>
    </div>

    <div class="challenge-modal-body">
        <div class="modal-description">
            <p>{{ $challenge->description ?: 'No description provided.' }}</p>
            @if ($challenge->slug === 'hidden-secrets')
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 10px;">
                        Investigation Evidence Files
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <a class="modal-evidence-link" href="{{ asset('storage/challenges/incident_report.log') }}" download="incident_report.log" style="display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>📄 <strong>incident_report.log</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(System Logs)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download &darr;</span>
                        </a>
                        <a class="modal-evidence-link" href="{{ asset('storage/challenges/memory_capture.bin') }}" download="memory_capture.bin" style="display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>💾 <strong>memory_capture.bin</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(Raw Memory Buffer)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download &darr;</span>
                        </a>
                        <a class="modal-evidence-link" href="{{ asset('storage/challenges/evidence_notes.txt') }}" download="evidence_notes.txt" style="display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>📝 <strong>evidence_notes.txt</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(Investigation Notes)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download &darr;</span>
                        </a>
                    </div>
                </div>
            @elseif ($challenge->slug === 'rsa-oracle')
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 8px;">
                        Interactive Oracle &amp; Evidence Files
                    </div>
                    <div style="background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; padding: 10px 12px; margin-bottom: 12px; font-family: monospace; font-size: 13px; color: var(--accent, #5ef285);">
                        <code>nc {{ env('ORACLE_HOST', request()->getHost()) }} {{ env('ORACLE_PORT', 5000) }}</code>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <a class="modal-evidence-link" href="{{ asset('storage/challenges/password.enc') }}" download="password.enc" style="display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>🔑 <strong>password.enc</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(Encrypted Password)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download &darr;</span>
                        </a>
                        <a class="modal-evidence-link" href="{{ asset('storage/challenges/secret.enc') }}" download="secret.enc" style="display: flex; align-items: center; justify-content: space-between; padding: 9px 12px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>📜 <strong>secret.enc</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(AES-256-CBC Message)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download &darr;</span>
                        </a>
                    </div>
                    <div style="margin-top: 12px;">
                        <a class="modal-link-external" href="{{ route('challenges.target', ['challenge' => $challenge]) }}" target="_blank" rel="noopener noreferrer"><strong>Open banking gateway portal</strong><span class="external-arrow">&nearr;</span></a>
                    </div>
                </div>
            @elseif ($challenge->slug === 'hidden-in-the-hounds')
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 10px;">
                        Evidence Image
                    </div>
                    <div style="text-align: center; margin-bottom: 12px;">
                        <img src="{{ asset('storage/challenges/popular_dog_breeds.png') }}" alt="Evidence Photograph" style="max-width: 100%; max-height: 260px; height: auto; border-radius: 6px; border: 1px solid var(--line, #2c352d); object-fit: contain; background: #000;">
                    </div>
                    <div>
                        <a class="modal-evidence-link" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>🖼️ <strong>popular_dog_breeds.png</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(1200 &times; 1200 PNG Image)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download Image &darr;</span>
                        </a>
                    </div>
                </div>
            @elseif ($challenge->slug === 'silent-evidence')
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 10px;">
                        Evidence Image
                    </div>
                    <div style="text-align: center; margin-bottom: 12px;">
                        <img src="{{ asset('storage/' . $challenge->attachment_path) }}" alt="Evidence Photograph" style="max-width: 100%; max-height: 260px; height: auto; border-radius: 6px; border: 1px solid var(--line, #2c352d); object-fit: contain; background: #000;">
                    </div>
                    <div>
                        <a class="modal-evidence-link" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>🖼️ <strong>silent_evidence.png</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(447 &times; 447 PNG Image)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download Image &darr;</span>
                        </a>
                    </div>
                </div>
            @elseif ($challenge->slug === 'the-silent-conversation')
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 10px;">
                        Network Traffic Capture
                    </div>
                    <div>
                        <a class="modal-evidence-link" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>📦 <strong>{{ $challenge->downloadFilename() }}</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(Packet Capture File)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download PCAP &darr;</span>
                        </a>
                    </div>
                </div>
            @elseif ($challenge->slug === 'memory-fragments' || str_ends_with(strtolower($challenge->downloadFilename()), '.bin'))
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 10px;">
                        Memory Dump Evidence
                    </div>
                    <div>
                        <a class="modal-evidence-link" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>💾 <strong>{{ $challenge->downloadFilename() }}</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(Synthetic Memory Capture)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download Evidence &darr;</span>
                        </a>
                    </div>
                </div>
            @elseif ($challenge->slug === 'the-unwritten-algorithm' || str_ends_with(strtolower($challenge->downloadFilename()), '.zip'))
                <div class="modal-evidence-files" style="margin-top: 16px; padding: 14px 16px; background: rgba(0,0,0,0.3); border: 1px solid var(--line, #2c352d); border-radius: 8px;">
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent, #5ef285); margin-bottom: 10px;">
                        Algorithm Dataset &amp; Package
                    </div>
                    <div>
                        <a class="modal-evidence-link" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: var(--panel-raised, #1d241d); border: 1px solid var(--line, #2c352d); border-radius: 6px; color: var(--text, #edf2e9); text-decoration: none; font-size: 13px;">
                            <span>📦 <strong>{{ $challenge->downloadFilename() }}</strong> <span style="font-size: 11px; color: var(--muted, #9ca79b);">(Samples &amp; Probe Tool)</span></span>
                            <span style="color: var(--accent, #5ef285); font-weight: bold; font-size: 12px;">Download Package &darr;</span>
                        </a>
                    </div>
                    @if (filled($targetUrl))
                        <div style="margin-top: 12px;">
                            <a class="modal-link-external" href="{{ $targetUrl }}" target="_blank" rel="noopener noreferrer"><strong>Open Verification Portal</strong><span class="external-arrow">&nearr;</span></a>
                        </div>
                    @endif
                </div>
            @elseif (filled($targetUrl))
                <p>
                    <a class="modal-link-external" href="{{ $targetUrl }}" target="_blank" rel="noopener noreferrer"><strong>Open challenge website</strong><span class="external-arrow">&nearr;</span></a>
                </p>
            @endif
        </div>

        @if (count($hints) > 0)
            <div class="modal-section">
                <h3 class="modal-section-heading">Hints <span class="modal-info-icon" title="Hints are optional">i</span></h3>
                <div class="modal-hints-list">
                    @foreach ($hints as $hint)
                        <details class="modal-hint-item">
                            <summary class="modal-hint-summary">
                                <span class="modal-hint-number">{{ $loop->iteration }}</span>
                                <span class="modal-hint-label">Hint {{ $loop->iteration }}</span>
                            </summary>
                            <p class="modal-hint-content">{{ $hint }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="modal-section" data-modal-submission>
            <h3 class="modal-section-heading">Submit flag</h3>
            <div data-modal-message role="status" aria-live="polite">
                @if (session('status'))
                    <div class="modal-success-message">{{ session('status') }}</div>
                @endif
                @error('flag')
                    <div class="modal-error-message">{{ $message }}</div>
                @enderror
            </div>
            @if ($isSolved)
                <div class="modal-solved-state">
                    <div class="modal-solved-icon">&check;</div>
                    <div class="modal-solved-text">
                        <strong>Challenge solved</strong>
                        <p>Points are only awarded once.</p>
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('challenges.submit', ['challenge' => $challenge]) }}" data-modal-flag-form>
                    @csrf
                    <div class="modal-flag-input-group">
                        <input class="modal-flag-input" id="modal-flag" name="flag" type="text" maxlength="255" placeholder="p4dCTF{...}" autocomplete="off" aria-label="Flag" required>
                        <button class="modal-flag-submit" type="submit">Submit</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if ($challenge->hasAttachment())
        <div class="challenge-modal-footer">
            <a class="modal-footer-workspace" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>
                @if (str_ends_with(strtolower($challenge->downloadFilename()), '.pcap'))
                    Download PCAP
                @elseif (str_ends_with(strtolower($challenge->downloadFilename()), '.bin') || $challenge->slug === 'memory-fragments')
                    Download Evidence
                @elseif (str_ends_with(strtolower($challenge->downloadFilename()), '.zip') || $challenge->slug === 'the-unwritten-algorithm')
                    Download Package
                @else
                    Download Image
                @endif
            </a>
        </div>
    @endif
</div>
