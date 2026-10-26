import os

search_dirs = [
    r"C:\Users\Admin\Downloads",
    r"C:\Users\Admin\Documents",
    r"C:\Users\Admin\Desktop",
    r"C:\Users\Admin\AppData\Local\Temp",
]

target_names = {
    'popular_dog_breeds.png',
    'silent_evidence.png',
    'the_silent_conversation.pcap',
    'memory_dump.bin',
    'the_unwritten_algorithm.zip',
    'incident_report.log',
    'memory_capture.bin',
    'evidence_notes.txt',
    'password.enc',
    'secret.enc'
}

found = {}

for root_dir in search_dirs:
    if not os.path.exists(root_dir):
        continue
    for root, dirs, files in os.walk(root_dir):
        for f in files:
            if f in target_names:
                full_path = os.path.join(root, f)
                if f not in found:
                    found[f] = []
                found[f].append((full_path, os.path.getsize(full_path)))

for name, paths in found.items():
    print(f"=== {name} ===")
    for p, sz in paths:
        print(f"  {sz} bytes: {p}")

