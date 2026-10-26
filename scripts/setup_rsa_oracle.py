#!/usr/bin/env python3
import os
import json
import subprocess

base_dir = "/var/www/html"
storage_dir = os.path.join(base_dir, "storage/app/public/challenges")
app_dir = os.path.join(base_dir, "storage/app")
os.makedirs(storage_dir, exist_ok=True)
os.makedirs(app_dir, exist_ok=True)

pem_path = os.path.join(app_dir, "rsa_oracle_key.pem")
if not os.path.exists(pem_path):
    subprocess.run(["openssl", "genrsa", "-out", pem_path, "1024"], check=True)
    os.chmod(pem_path, 0o600)

# Extract N, e, d
cmd = ["openssl", "rsa", "-in", pem_path, "-text", "-noout"]
proc = subprocess.run(cmd, capture_output=True, text=True, check=True)
output = proc.stdout

def extract_hex_field(text, start_label, end_labels):
    start = text.find(start_label)
    if start == -1:
        return None
    start += len(start_label)
    end = len(text)
    for el in end_labels:
        p = text.find(el, start)
        if p != -1 and p < end:
            end = p
    chunk = text[start:end]
    clean_hex = "".join(c for c in chunk if c in "0123456789abcdefABCDEF")
    return clean_hex

n_hex = extract_hex_field(output, "modulus:", ["publicExponent:", "privateExponent:"])
d_hex = extract_hex_field(output, "privateExponent:", ["prime1:", "prime2:"])

# e is usually 65537
e = 65537
n = int(n_hex, 16)
d = int(d_hex, 16)

password = "FintechAuth2026!BankMasterKey"
flag = "p4dCTF{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}"

# Encrypt password with RSA: c = m^e mod N
m = int.from_bytes(password.encode('utf-8'), 'big')
c = pow(m, e, n)
c_hex = hex(c)

# Save password.enc
password_enc_path = os.path.join(storage_dir, "password.enc")
with open(password_enc_path, "w") as f:
    f.write(c_hex + "\n")
os.chmod(password_enc_path, 0o644)
print(f"Written: password.enc ({c_hex[:30]}...)")

# Secret document
secret_doc = f"""============================================================
P4DC BANKING GATEWAY - INTERBANK SETTLEMENT NOTIFICATION
============================================================
TRANSACTION ID : TX-2026-9844-FINTECH-01
ORIGINATOR     : P4DC CENTRAL SETTLEMENT
RECIPIENT      : FINTECH CORP LIQUIDITY POOL
AMOUNT         : USD 14,850,000.00
AUTH TIMESTAMP : 2026-10-18 01:45:00 UTC
STATUS         : AUTHORIZED & VERIFIED

CLEARANCE FLAG : {flag}
============================================================
"""

# Encrypt with OpenSSL AES-256-CBC PBKDF2
secret_enc_path = os.path.join(storage_dir, "secret.enc")
enc_cmd = [
    "openssl", "enc", "-aes-256-cbc", "-salt", "-pbkdf2",
    "-pass", f"pass:{password}",
    "-out", secret_enc_path
]
proc = subprocess.run(enc_cmd, input=secret_doc.encode('utf-8'), check=True)
os.chmod(secret_enc_path, 0o644)
print(f"Written: secret.enc ({os.path.getsize(secret_enc_path)} bytes)")

# Verify decryption with OpenSSL
dec_cmd = [
    "openssl", "enc", "-d", "-aes-256-cbc", "-pbkdf2",
    "-pass", f"pass:{password}",
    "-in", secret_enc_path
]
dec_proc = subprocess.run(dec_cmd, capture_output=True, text=True, check=True)
if dec_proc.stdout == secret_doc:
    print("VERIFICATION SUCCESS: OpenSSL decrypted secret.enc matches original message!")
else:
    print("VERIFICATION FAILED: Content mismatch!")

# Save parameters to rsa_oracle_data.json
data = {
    "n_hex": hex(n),
    "e": e,
    "d_hex": hex(d),
    "c_hex": c_hex,
    "flag": flag,
    "password": password
}
data_path = os.path.join(app_dir, "rsa_oracle_data.json")
with open(data_path, "w") as f:
    json.dump(data, f, indent=2)
os.chmod(data_path, 0o600)
print(f"Saved server parameters to: {data_path}")
