<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>P4DC Banking Gateway — Cryptographic Oracle</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
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
            max-width: 960px;
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
            align-items: center;
            gap: 12px;
        }
        .badge {
            background: var(--panel-raised);
            color: var(--accent);
            border: 1px solid var(--border);
            padding: 4px 10px;
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
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .terminal-box {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 16px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            color: var(--accent);
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .file-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 14px;
        }
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--panel-raised);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text);
            transition: all 0.2s;
        }
        .file-item:hover {
            border-color: var(--accent);
            background: #1c271e;
        }
        .file-info {
            display: flex;
            flex-direction: column;
        }
        .file-name {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            font-size: 14px;
        }
        .file-desc {
            font-size: 12px;
            color: var(--muted);
        }
        .file-action {
            color: var(--accent);
            font-weight: 700;
            font-size: 13px;
        }
        .specs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 12px;
        }
        .specs-table td {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .specs-table td:first-child {
            color: var(--muted);
            width: 38%;
        }
        .specs-table td:last-child {
            font-family: 'JetBrains Mono', monospace;
            color: #fff;
        }
        .notice {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 6px;
            padding: 14px;
            color: #fde68a;
            font-size: 13px;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="brand">
                <span class="badge">Cryptography // Medium</span>
                <h1 class="title">P4DC Central Banking Gateway</h1>
            </div>
            <a class="back-link" href="{{ route('challenges') }}">&larr; Return to Challenges</a>
        </header>

        <main>
            <div class="grid">
                <!-- Oracle Access Panel -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-size: 20px;">⚡</span>
                        <h2 class="card-title">Live Oracle Service</h2>
                    </div>
                    <p style="font-size: 14px; color: var(--muted);">
                        The bank provides an active cryptographic gateway that accepts chosen plaintext inputs for encryption and transaction verification. Connect via terminal to query the oracle:
                    </p>
                    <div class="terminal-box">
                        <code>nc {{ env('ORACLE_HOST', request()->getHost()) }} {{ env('ORACLE_PORT', 5000) }}</code>
                    </div>
                    <p style="font-size: 12px; color: var(--muted); margin-top: 10px;">
                        Alternate endpoint: <code>nc {{ env('ORACLE_HOST', request()->getHost()) }} {{ env('ORACLE_PORT', 5000) }}</code>
                    </p>
                    <div class="notice">
                        <strong>Security Policy Notice:</strong> The oracle accepts chosen plaintext inputs and arbitrary ciphertext queries, but directly decrypting the master password ciphertext is prohibited.
                    </div>
                </div>

                <!-- Intercepted Evidence -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-size: 20px;">📦</span>
                        <h2 class="card-title">Intercepted Evidence</h2>
                    </div>
                    <p style="font-size: 14px; color: var(--muted);">
                        Two encrypted artifacts were captured from the transmission buffer between the bank gateway and the fintech provider:
                    </p>
                    <div class="file-list">
                        <a class="file-item" href="{{ asset('storage/challenges/password.enc') }}" download="password.enc">
                            <div class="file-info">
                                <span class="file-name">password.enc</span>
                                <span class="file-desc">RSA-encrypted authorization credential (Hex)</span>
                            </div>
                            <span class="file-action">Download &darr;</span>
                        </a>
                        <a class="file-item" href="{{ asset('storage/challenges/secret.enc') }}" download="secret.enc">
                            <div class="file-info">
                                <span class="file-name">secret.enc</span>
                                <span class="file-desc">AES-256-CBC encrypted settlement message (Binary)</span>
                            </div>
                            <span class="file-action">Download &darr;</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Technical Specifications -->
            <div class="card">
                <div class="card-header">
                    <span style="font-size: 20px;">🔐</span>
                    <h2 class="card-title">Cryptographic Architecture</h2>
                </div>
                <table class="specs-table">
                    <tr>
                        <td>Asymmetric Scheme:</td>
                        <td>RSA 1024-bit (Textbook / Unpadded)</td>
                    </tr>
                    <tr>
                        <td>Public Exponent (e):</td>
                        <td>65537 (0x10001)</td>
                    </tr>
                    <tr>
                        <td>Symmetric Cipher:</td>
                        <td>AES-256-CBC</td>
                    </tr>
                    <tr>
                        <td>Key Derivation:</td>
                        <td>PBKDF2 with HMAC-SHA256 (Salt embedded in secret.enc header)</td>
                    </tr>
                    <tr>
                        <td>Decryption Utility:</td>
                        <td><code>openssl enc -d -aes-256-cbc -pbkdf2</code></td>
                    </tr>
                </table>
            </div>
        </main>
    </div>
</body>
</html>

