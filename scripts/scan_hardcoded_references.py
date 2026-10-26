import os
import re

PATTERNS = {
    'localhost': re.compile(r'localhost', re.IGNORECASE),
    '127.0.0.1': re.compile(r'127\.0\.0\.1'),
    'local_ports': re.compile(r':(8080|5000|5433|5432|3000|8000)\b'),
    'http_insecure': re.compile(r'http://(?!localhost|127\.0\.0\.1|www\.w3\.org)', re.IGNORECASE),
    'local_paths': re.compile(r'[a-zA-Z]:\\[Users|Documents|PADC]', re.IGNORECASE),
    'debug_calls': re.compile(r'\b(dd\(|dump\(|var_dump\(|console\.log\()'),
}

EXCLUDE_DIRS = {'.git', 'vendor', 'node_modules', 'storage', '.qodo', '.backup_pre_source_trail'}
EXCLUDE_EXTS = {'.sql', '.pcap', '.bin', '.png', '.jpg', '.jpeg', '.ico', '.zip'}

results = {k: [] for k in PATTERNS}

base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

for root, dirs, files in os.walk(base_dir):
    dirs[:] = [d for d in dirs if d not in EXCLUDE_DIRS]
    for file in files:
        ext = os.path.splitext(file)[1].lower()
        if ext in EXCLUDE_EXTS:
            continue
        rel_path = os.path.relpath(os.path.join(root, file), base_dir)
        # Skip scripts/test_*, scripts/baseline_audit.php, etc. from polluting production findings, but check app/ resources/ routes/ config/
        full_path = os.path.join(root, file)
        try:
            with open(full_path, 'r', encoding='utf-8', errors='ignore') as f:
                for line_no, line in enumerate(f, 1):
                    for name, pat in PATTERNS.items():
                        if pat.search(line):
                            results[name].append((rel_path, line_no, line.strip()))
        except Exception as e:
            pass

print("=== SCAN FINDINGS ===")
for name, matches in results.items():
    print(f"\n--- {name.upper()} ({len(matches)} occurrences) ---")
    for path, line_no, line in matches[:30]: # limit display
        print(f"  {path}:{line_no}: {line[:120]}")
    if len(matches) > 30:
        print(f"  ... and {len(matches) - 30} more")

