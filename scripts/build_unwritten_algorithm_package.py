#!/usr/bin/env python3
"""
Generate the complete downloadable package and samples for
'The Unwritten Algorithm' challenge.
"""

import os
import json
import zipfile
import hashlib
import hmac
import struct
import base64

FLAG = "p4dCTF{h1dd3n_l0g1c_r3v34l3d}"
CHALLENGE_INPUT = "P4DC{c0gn1t1v3_tr4nsf0rm_v2_2026}"

def transform(s: str) -> str:
    if not s:
        return ""
    L = len(s)

    # Stage 1: Polyalphabetic Dynamic Rotation
    s1 = []
    for i, c in enumerate(s):
        if 'A' <= c <= 'Z':
            v = ord(c) - 65
            r = (i * 5 + 3) % 26
            s1.append(chr(65 + ((v + r) % 26)))
        elif 'a' <= c <= 'z':
            v = ord(c) - 97
            r = (i * 7 + 11) % 26
            s1.append(chr(97 + ((v + r) % 26)))
        elif '0' <= c <= '9':
            d = ord(c) - 48
            if i % 2 == 0:
                d_new = (d + 4) % 10
            else:
                d_new = (10 + d - 3) % 10
            s1.append(chr(48 + d_new))
        else:
            s1.append(c)

    # Stage 2: Pairwise Adjacent Swap
    s2 = list(s1)
    for i in range(0, L - 1, 2):
        s2[i], s2[i+1] = s2[i+1], s2[i]

    # Stage 3: Selective Vowel Case Inversion
    vowels = set("AEIOUaeiou")
    s3 = []
    for c in s2:
        if c in vowels:
            s3.append(c.lower() if c.isupper() else c.upper())
        else:
            s3.append(c)
    s3_str = "".join(s3)

    # Stage 4: Global Parity / Tri-state Folding based on sum(ord(c)) % 3
    ascii_sum = sum(ord(c) for c in s)
    mod = ascii_sum % 3

    if mod == 0:
        res = s3_str[::-1]
    elif mod == 1:
        mid = L // 2
        res = s3_str[mid:] + s3_str[:mid]
    else:
        if L >= 3:
            shift = 3 % L
            res = s3_str[-shift:] + s3_str[:-shift]
        else:
            res = s3_str[::-1]

    return res

# 1. Generate 24 Representative Samples
SAMPLE_INPUTS = [
    "",
    "A",
    "B",
    "Z",
    "a",
    "b",
    "z",
    "0",
    "5",
    "9",
    "AB",
    "ab",
    "12",
    "X!",
    "cat",
    "dog",
    "echo",
    "test",
    "cyber",
    "P4DC",
    "Algorithm",
    "CTF2026",
    "AAAA",
    "xyzxyz"
]

samples = []
for inp in SAMPLE_INPUTS:
    out = transform(inp)
    ascii_sum = sum(ord(c) for c in inp)
    samples.append({
        "input": inp,
        "output": out,
        "length": len(inp)
    })

# Compute correct output for challenge input
TARGET_OUTPUT = transform(CHALLENGE_INPUT)

# Encrypt the flag using keystream derived from TARGET_OUTPUT
key = hashlib.sha256(TARGET_OUTPUT.encode('utf-8')).digest()
flag_bytes = FLAG.encode('utf-8')

keystream = bytearray()
counter = 0
while len(keystream) < len(flag_bytes):
    block = hashlib.sha256(key + struct.pack(">I", counter)).digest()
    keystream.extend(block)
    counter += 1

ciphertext = bytes(a ^ b for a, b in zip(flag_bytes, keystream[:len(flag_bytes)]))
auth_tag = hmac.new(key, ciphertext, hashlib.sha256).digest()[:16]

ciphertext_b64 = base64.b64encode(ciphertext).decode('utf-8')
tag_b64 = base64.b64encode(auth_tag).decode('utf-8')

# Verify decrypt works
verify_key = hashlib.sha256(TARGET_OUTPUT.encode('utf-8')).digest()
verify_tag = hmac.new(verify_key, ciphertext, hashlib.sha256).digest()[:16]
assert verify_tag == auth_tag
decrypted = bytes(a ^ b for a, b in zip(ciphertext, keystream[:len(ciphertext)])).decode('utf-8')
assert decrypted == FLAG

# 2. Write Probe script (algorithm_probe.py)
probe_code = f'''#!/usr/bin/env python3
"""
P4DC CTF — The Unwritten Algorithm
Offline Probe & Verification Utility

Use this utility to inspect public samples and verify your candidate output
for the challenge sequence.
"""

import sys
import json
import base64
import hashlib
import hmac
import struct

CHALLENGE_INPUT = "{CHALLENGE_INPUT}"
CIPHERTEXT_B64 = "{ciphertext_b64}"
TAG_B64 = "{tag_b64}"

def verify_output(candidate: str):
    candidate = candidate.strip()
    if not candidate:
        print("[-] Error: Candidate output cannot be empty.")
        return False

    key = hashlib.sha256(candidate.encode('utf-8')).digest()
    ciphertext = base64.b64decode(CIPHERTEXT_B64)
    expected_tag = base64.b64decode(TAG_B64)

    calculated_tag = hmac.new(key, ciphertext, hashlib.sha256).digest()[:16]

    if not hmac.compare_digest(calculated_tag, expected_tag):
        print("[-] Verification failed: Candidate output does NOT match the algorithm's expected output.")
        print("[-] Double-check your inferred shift rules, character permutations, vowel handling, and parity fold.")
        return False

    # Keystream decryption
    keystream = bytearray()
    counter = 0
    while len(keystream) < len(ciphertext):
        block = hashlib.sha256(key + struct.pack(">I", counter)).digest()
        keystream.extend(block)
        counter += 1

    decrypted_flag = bytes(a ^ b for a, b in zip(ciphertext, keystream[:len(ciphertext)])).decode('utf-8')
    print("[+] ========================================================")
    print("[+] SUCCESS! Transformation output verified!")
    print(f"[+] Recovered Flag: {{decrypted_flag}}")
    print("[+] ========================================================")
    return True

def show_samples():
    try:
        with open("samples.json", "r") as f:
            data = json.load(f)
        print(f"Loaded {{len(data)}} sample pairs from samples.json:\\n")
        for s in data[:10]:
            print(f"  Input : {{repr(s['input']):<20}} -> Output: {{repr(s['output'])}}")
        if len(data) > 10:
            print(f"  ... and {{len(data) - 10}} more in samples.json")
    except Exception as e:
        print(f"[-] Could not read samples.json: {{e}}")

def main():
    if len(sys.argv) < 2:
        print("Usage:")
        print("  python3 algorithm_probe.py --samples")
        print("  python3 algorithm_probe.py --challenge")
        print("  python3 algorithm_probe.py --verify \\"<your_computed_output>\\"")
        sys.exit(1)

    cmd = sys.argv[1]
    if cmd == "--samples":
        show_samples()
    elif cmd == "--challenge":
        print("==================================================")
        print("CHALLENGE SEQUENCE:")
        print(f"  Input: {{CHALLENGE_INPUT}}")
        print("Compute the transformed output for this input, then run:")
        print("  python3 algorithm_probe.py --verify \\"<your_output>\\"")
        print("==================================================")
    elif cmd == "--verify":
        if len(sys.argv) < 3:
            print("[-] Error: Missing candidate output argument.")
            sys.exit(1)
        candidate = sys.argv[2]
        verify_output(candidate)
    else:
        print(f"[-] Unknown command: {{cmd}}")
        sys.exit(1)

if __name__ == "__main__":
    main()
'''

# 3. Write Problem Description (target_challenge.txt)
readme_text = f'''# P4DC CTF — The Unwritten Algorithm
Category: Miscellaneous
Difficulty: Hard (100 pts)
Author: P4DC CTF

## Overview
A proprietary black-box data transformer is observed converting arbitrary ASCII inputs
into transformed outputs. The underlying algorithm specification was lost, but 24
input/output samples have been captured in `samples.json`.

## Your Mission
1. Analyze the sample input/output mappings in `samples.json`.
2. Determine the sequence of transformation rules (rotations, transpositions, symbol handling, and global parity folding).
3. Implement the reverse-engineered transformation logic in `solution_template.py`.
4. Compute the expected output for the challenge sequence:
   Target Input: "{CHALLENGE_INPUT}"
5. Verify your result locally using:
   python3 algorithm_probe.py --verify "<your_computed_output>"
   or submit it to the online Algorithm Verification Portal.
6. Upon successful verification, the utility will unlock the official flag!

Files in this archive:
- `samples.json`: 24 representative input/output pairs.
- `algorithm_probe.py`: Offline verification tool.
- `solution_template.py`: Starter template for implementing your solution.
- `target_challenge.txt`: Challenge instructions.
'''

# 4. Write Solution Template (solution_template.py)
template_code = '''#!/usr/bin/env python3
"""
P4DC CTF — The Unwritten Algorithm
Solution Starter Template

Implement the inferred transformation algorithm in `transform(s)`.
"""

import json

def transform(s: str) -> str:
    """
    Reverse-engineered transformation algorithm.
    TODO: Fill in your implementation based on the rules inferred from samples.json.
    """
    # [YOUR IMPLEMENTATION HERE]
    return s

def test_samples():
    with open("samples.json", "r") as f:
        samples = json.load(f)

    passed = 0
    failed = 0
    for idx, item in enumerate(samples):
        inp = item["input"]
        expected = item["output"]
        actual = transform(inp)
        if actual == expected:
            passed += 1
        else:
            failed += 1
            if failed <= 3:
                print(f"[FAIL] Sample #{idx+1} {repr(inp)}: expected {repr(expected)}, got {repr(actual)}")

    print(f"\\nResult: {passed}/{len(samples)} samples passed.")
    if failed == 0:
        challenge_input = "P4DC{c0gn1t1v3_tr4nsf0rm_v2_2026}"
        ans = transform(challenge_input)
        print("\\n[+] All samples passed!")
        print(f"[+] Computed Challenge Output: {ans}")
        print(f"[+] Run: python3 algorithm_probe.py --verify {repr(ans)}")

if __name__ == "__main__":
    test_samples()
'''

# 5. Build ZIP file
base_dir = os.path.dirname(os.path.abspath(__file__))
target_dir = os.path.join(base_dir, "../storage/app/public/challenges")
os.makedirs(target_dir, exist_ok=True)
zip_path = os.path.join(target_dir, "the_unwritten_algorithm.zip")

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as z:
    z.writestr("samples.json", json.dumps(samples, indent=2))
    z.writestr("algorithm_probe.py", probe_code)
    z.writestr("target_challenge.txt", readme_text)
    z.writestr("solution_template.py", template_code)

print(f"[+] Successfully built: {zip_path}")
print(f"[+] Size: {os.path.getsize(zip_path)} bytes")
print(f"[+] Target Output: {TARGET_OUTPUT}")
