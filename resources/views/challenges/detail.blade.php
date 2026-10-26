@extends('layouts.app')

@section('title', $challenge->title)
@section('section', 'CHALLENGES')

@section('content')
    @php
        $category = config('ctf.categories.' . $challenge->category, $challenge->category);
        $difficulty = config('ctf.difficulties.' . $challenge->difficulty, $challenge->difficulty);
    @endphp

    @if (session('status'))
        <p class="form-notice dashboard-notice" role="status">{{ session('status') }}</p>
    @endif

    <section class="page-intro">
        <div class="eyebrow">
            <a href="{{ route('challenges') }}">CHALLENGES</a> <span class="eyebrow-divider">/</span> {{ strtoupper($category) }}
        </div>
        <h1>{{ $challenge->title }}</h1>
        <p>{{ $challenge->description ?: 'No description provided.' }}</p>
    </section>

    <section class="challenge-meta" aria-label="Challenge details">
        <div class="meta-card"><span>CATEGORY</span><strong>{{ $category }}</strong></div>
        <div class="meta-card"><span>DIFFICULTY</span><strong>{{ $difficulty }}</strong></div>
        <div class="meta-card points-card"><span>POINTS</span><strong>{{ number_format($challenge->points) }}</strong></div>
        <div class="meta-card"><span>STATUS</span><strong>{{ $isSolved ? 'Solved' : 'Open' }}</strong></div>
    </section>

    <section class="challenge-target-panel">
        @if ($challenge->slug === 'hidden-secrets')
            <div>
                <div class="eyebrow">EVIDENCE ARTIFACTS</div>
                <h2>Investigation Files</h2>
                <p>Download the captured evidence artifacts and analyze them to uncover concealed data.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px;">
                    <a class="button button-outline" href="{{ asset('storage/challenges/incident_report.log') }}" download="incident_report.log">📄 incident_report.log &darr;</a>
                    <a class="button button-outline" href="{{ asset('storage/challenges/memory_capture.bin') }}" download="memory_capture.bin">💾 memory_capture.bin &darr;</a>
                    <a class="button button-outline" href="{{ asset('storage/challenges/evidence_notes.txt') }}" download="evidence_notes.txt">📝 evidence_notes.txt &darr;</a>
                </div>
            </div>
        @elseif ($challenge->slug === 'rsa-oracle')
            <div>
                <div class="eyebrow">INTERACTIVE ORACLE &amp; ARTIFACTS</div>
                <h2>Banking Gateway Oracle</h2>
                <p>Connect to the live RSA oracle service via terminal: <code style="color: var(--accent); font-weight: bold; background: rgba(0,0,0,0.4); padding: 3px 8px; border-radius: 4px;">nc {{ env('ORACLE_HOST', request()->getHost()) }} {{ env('ORACLE_PORT', 5000) }}</code></p>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px;">
                    <a class="button button-outline" href="{{ asset('storage/challenges/password.enc') }}" download="password.enc">🔑 password.enc &darr;</a>
                    <a class="button button-outline" href="{{ asset('storage/challenges/secret.enc') }}" download="secret.enc">📜 secret.enc &darr;</a>
                    <a class="button button-outline" href="{{ route('challenges.target', ['challenge' => $challenge]) }}" target="_blank" rel="noopener noreferrer">Open Gateway Portal &nearr;</a>
                </div>
            </div>
        @elseif ($challenge->slug === 'hidden-in-the-hounds')
            <div>
                <div class="eyebrow">EVIDENCE ARTIFACT</div>
                <h2>Evidence Photograph</h2>
                <p>Inspect the photograph retrieved from the archive. Look closely at the pixel data to extract the hidden transmission.</p>
                <div style="text-align: center; margin: 16px 0;">
                    <img src="{{ asset('storage/challenges/popular_dog_breeds.png') }}" alt="Evidence Photograph" style="max-width: 100%; max-height: 340px; height: auto; border-radius: 6px; border: 1px solid var(--line, #2c352d); object-fit: contain; background: #000;">
                </div>
                <div style="margin-top: 14px;">
                    <a class="button button-outline" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>🖼️ Download Image (popular_dog_breeds.png) &darr;</a>
                </div>
            </div>
        @elseif ($challenge->slug === 'silent-evidence')
            <div>
                <div class="eyebrow">EVIDENCE ARTIFACT</div>
                <h2>Evidence Photograph</h2>
                <p>Inspect the photograph retrieved from the archive. Look closely at the pixel data to extract the hidden transmission.</p>
                <div style="text-align: center; margin: 16px 0;">
                    <img src="{{ asset('storage/' . $challenge->attachment_path) }}" alt="Evidence Photograph" style="max-width: 100%; max-height: 340px; height: auto; border-radius: 6px; border: 1px solid var(--line, #2c352d); object-fit: contain; background: #000;">
                </div>
                <div style="margin-top: 14px;">
                    <a class="button button-outline" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>🖼️ Download Image (silent_evidence.png) &darr;</a>
                </div>
            </div>
        @elseif ($challenge->slug === 'the-silent-conversation')
            <div>
                <div class="eyebrow">NETWORK CAPTURE ARTIFACT</div>
                <h2>Traffic Capture (PCAP)</h2>
                <p>Download and inspect the network packet capture. Filter and follow the TCP conversation to extract the hidden transmission.</p>
                <div style="margin-top: 14px;">
                    <a class="button button-outline" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>📦 Download PCAP ({{ $challenge->downloadFilename() }}) &darr;</a>
                </div>
            </div>
        @elseif ($challenge->slug === 'memory-fragments' || str_ends_with(strtolower($challenge->downloadFilename()), '.bin'))
            <div>
                <div class="eyebrow">FORENSIC ARTIFACT</div>
                <h2>Memory Dump Capture</h2>
                <p>Download and analyze the synthetic memory dump to uncover the process evidence and recover the flag fragments.</p>
                <div style="margin-top: 14px;">
                    <a class="button button-outline" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>💾 Download Evidence ({{ $challenge->downloadFilename() }}) &darr;</a>
                </div>
            </div>
        @elseif ($challenge->slug === 'the-unwritten-algorithm' || str_ends_with(strtolower($challenge->downloadFilename()), '.zip'))
            <div>
                <div class="eyebrow">CHALLENGE PACKAGE</div>
                <h2>Algorithm Dataset &amp; Probe Tool</h2>
                <p>Download the challenge package containing sample input/output mappings, the verification probe utility, and problem specifications.</p>
                <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 14px;">
                    <a class="button button-outline" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>📦 Download Package ({{ $challenge->downloadFilename() }}) &darr;</a>
                    @if (filled($targetUrl))
                        <a class="button button-outline" href="{{ $targetUrl }}" target="_blank" rel="noopener noreferrer">Open Verification Portal &nearr;</a>
                    @endif
                </div>
            </div>
        @else
            <div>
                <div class="eyebrow">TARGET</div>
                <h2>Challenge Website</h2>
                <p>Open the target in a new tab and investigate it.</p>
            </div>
            <a class="button button-outline" href="{{ $targetUrl }}" target="_blank" rel="noopener noreferrer">Open Challenge Website <span aria-hidden="true">&nearr;</span></a>
        @endif
    </section>

    <section class="submission-panel">
        <div class="eyebrow">SUBMISSION</div>
        <h2>Submit Flag</h2>
        @if ($isSolved)
            <p>You already solved this challenge. Points are only awarded once.</p>
        @else
            <form class="submission-form" method="POST" action="{{ route('challenges.submit', ['challenge' => $challenge]) }}">
                @csrf
                <label for="flag">Flag</label>
                <input id="flag" name="flag" type="text" maxlength="255" placeholder="p4dCTF{...}" value="{{ old('flag') }}" autocomplete="off" required>
                @error('flag')<p class="field-error">{{ $message }}</p>@enderror
                <button class="button button-primary" type="submit">Submit flag <span aria-hidden="true">&rarr;</span></button>
            </form>
        @endif
    </section>

    @if (count($hints) > 0)
        <section class="hints-panel">
            <div class="eyebrow">HINTS</div>
            <h2>Need a nudge?</h2>
            <div class="hint-list">
                @foreach ($hints as $hint)
                    <details class="hint-item">
                        <summary>Hint {{ $loop->iteration }}</summary>
                        <p>{{ $hint }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif
@endsection
