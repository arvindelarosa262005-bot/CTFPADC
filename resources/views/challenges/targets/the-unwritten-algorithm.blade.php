<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>The Unwritten Algorithm — Verification Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --bg: #090d0a;
            --panel: #111712;
            --panel-raised: #18221a;
            --border: #233326;
            --accent: #5ef285;
            --accent-glow: rgba(94, 242, 133, 0.2);
            --text: #edf2e9;
            --muted: #8b9a8c;
            --code-bg: #060907;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.6;
            padding: 24px;
        }
        .container {
            max-width: 920px;
            margin: 0 auto;
            width: 100%;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 30px;
        }
        .brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .badge {
            align-self: flex-start;
            background: var(--panel-raised);
            color: var(--accent);
            border: 1px solid var(--border);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
        }
        .back-link {
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--accent); }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
        }
        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 24px;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .terminal-box {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 14px 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: var(--accent);
            margin: 12px 0;
            word-break: break-all;
        }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .button-primary {
            background: var(--accent);
            color: #050a06;
            border-color: var(--accent);
        }
        .button-primary:hover {
            background: #4de674;
            box-shadow: 0 0 12px var(--accent-glow);
        }
        .button-outline {
            background: var(--panel-raised);
            color: var(--text);
            border-color: var(--border);
        }
        .button-outline:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 16px 0;
        }
        .input-group label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .input-field {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px 14px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            color: #fff;
            outline: none;
            transition: border-color 0.2s;
        }
        .input-field:focus {
            border-color: var(--accent);
            box-shadow: 0 0 8px var(--accent-glow);
        }
        .alert {
            padding: 14px;
            border-radius: 6px;
            font-size: 13px;
            margin-top: 14px;
            display: none;
        }
        .alert-success {
            background: rgba(94, 242, 133, 0.1);
            border: 1px solid var(--accent);
            color: var(--accent);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: #fca5a5;
        }
        .flag-box {
            margin-top: 16px;
            padding: 16px;
            background: var(--code-bg);
            border: 1px solid var(--accent);
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            color: var(--accent);
            text-align: center;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <span class="badge">Miscellaneous // Hard (100 Pts)</span>
                <h1 class="title">The Unwritten Algorithm</h1>
            </div>
            <a class="back-link" href="{{ route('challenges') }}">&larr; Return to Challenges</a>
        </header>

        <main>
            <div class="grid">
                <!-- Challenge Sequence -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-size: 18px;">🎯</span>
                        <h2 class="card-title">Challenge Sequence</h2>
                    </div>
                    <p style="font-size: 13px; color: var(--muted);">
                        Infer the transformation rules using the 24 sample pairs in the package, then compute the expected output for this challenge input:
                    </p>
                    <div class="terminal-box">
                        <strong>Input:</strong> P4DC{c0gn1t1v3_tr4nsf0rm_v2_2026}
                    </div>
                    <p style="font-size: 12px; color: var(--muted); margin-top: 10px;">
                        The algorithm combines polyalphabetic modular rotation, pairwise permutations, vowel toggling, and global parity folding.
                    </p>
                    <div style="margin-top: 16px;">
                        <a class="button button-outline" href="{{ route('challenges.download', ['challenge' => $challenge]) }}" download>
                            📦 Download Package (the_unwritten_algorithm.zip) &darr;
                        </a>
                    </div>
                </div>

                <!-- Verification Form -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-size: 18px;">⚡</span>
                        <h2 class="card-title">Live Verification Portal</h2>
                    </div>
                    <p style="font-size: 13px; color: var(--muted);">
                        Submit your computed output for the challenge sequence. If verified, the system unlocks the official flag.
                    </p>
                    <form id="verify-form">
                        <div class="input-group">
                            <label for="candidate-output">Computed Transformed Output</label>
                            <input class="input-field" id="candidate-output" type="text" placeholder="Enter output..." autocomplete="off" required>
                        </div>
                        <button class="button button-primary" id="verify-btn" type="submit">Verify Output &rarr;</button>
                    </form>

                    <div class="alert alert-error" id="error-alert"></div>
                    <div class="alert alert-success" id="success-alert">
                        <div><strong>Verification Passed!</strong> All algorithm stages and parity conditions satisfied.</div>
                        <div class="flag-box" id="flag-display"></div>
                        <p style="font-size: 11px; color: var(--muted); margin-top: 10px; text-align: center;">
                            Submit this flag in the floating challenge modal to record your solve and claim 100 points.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.getElementById('verify-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('verify-btn');
            const errBox = document.getElementById('error-alert');
            const successBox = document.getElementById('success-alert');
            const flagDisplay = document.getElementById('flag-display');
            const candidate = document.getElementById('candidate-output').value.trim();

            errBox.style.display = 'none';
            successBox.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Verifying...';

            try {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const resp = await fetch("{{ route('challenges.target.verify', ['challenge' => $challenge]) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ candidate_output: candidate })
                });

                const data = await resp.json();
                if (resp.ok && data.success) {
                    successBox.style.display = 'block';
                    flagDisplay.textContent = data.flag;
                } else {
                    errBox.textContent = data.message || 'Output mismatch. Verify your inferred transformation rules.';
                    errBox.style.display = 'block';
                }
            } catch (err) {
                errBox.textContent = 'Connection error during verification.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Verify Output →';
            }
        });
    </script>
</body>
</html>

