#!/usr/bin/env python3
"""
Exploit verification script for rsa_oracle challenge
"""
import socket
import re
import subprocess

def recv_until(s, prompt):
    buf = b""
    while prompt.encode() not in buf:
        chunk = s.recv(1024)
        if not chunk:
            break
        buf += chunk
    return buf.decode(errors='ignore')

def main():
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.connect(("localhost", 5000))

    banner = recv_until(s, "Enter option [1-4]> ")
    print("--- ORACLE BANNER RECEIVED ---")
    
    # Extract N and c from banner
    n_match = re.search(r"Modulus N:\s*(0x[0-9a-fA-F]+)", banner)
    c_match = re.search(r"Password Ciphertext c:\s*(0x[0-9a-fA-F]+)", banner)
    
    assert n_match and c_match, "Failed to parse N or c from banner!"
    N = int(n_match.group(1), 16)
    E = 65537
    C_TARGET = int(c_match.group(1), 16)
    
    print(f"Modulus N bit length: {N.bit_length()}")
    print(f"Intercepted Ciphertext c: {hex(C_TARGET)[:30]}...")

    # Step 1: Verify direct decryption is blocked
    s.sendall(b"2\n")
    recv_until(s, "Enter ciphertext to decrypt (hex): ")
    s.sendall(hex(C_TARGET).encode() + b"\n")
    resp_blocked = recv_until(s, "Enter option [1-4]> ")
    print("Direct decryption response:\n", resp_blocked.strip())
    assert "SECURITY POLICY VIOLATION" in resp_blocked or "Access Denied" in resp_blocked

    # Step 2: Blinding attack
    # Choose r = 2
    r = 2
    r_e = pow(r, E, N)
    c_blinded = (C_TARGET * r_e) % N

    print(f"Blinding with factor r = {r}...")
    print(f"Blinded ciphertext c': {hex(c_blinded)[:30]}...")

    s.sendall(b"2\n")
    recv_until(s, "Enter ciphertext to decrypt (hex): ")
    s.sendall(hex(c_blinded).encode() + b"\n")
    resp_blinded = recv_until(s, "Enter option [1-4]> ")
    print("Blinded decryption response:\n", resp_blinded.strip())

    m_prime_match = re.search(r"Decrypted Plaintext \(hex\):\s*(0x[0-9a-fA-F]+)", resp_blinded)
    assert m_prime_match, "Failed to get decrypted m' from oracle!"
    m_prime = int(m_prime_match.group(1), 16)

    # Step 3: Unblind
    # m = m' * r^(-1) mod N
    r_inv = pow(r, -1, N)
    m = (m_prime * r_inv) % N

    password_bytes = m.to_bytes((m.bit_length() + 7) // 8, 'big')
    recovered_password = password_bytes.decode('utf-8', errors='ignore')
    print(f"\n[+] RECOVERED PASSWORD: {recovered_password}")
    assert recovered_password == "FintechAuth2026!BankMasterKey"

    # Step 4: Decrypt secret.enc using OpenSSL and recovered password
    # Path to secret.enc
    secret_path = "/var/www/html/storage/app/public/challenges/secret.enc"
    dec_cmd = [
        "openssl", "enc", "-d", "-aes-256-cbc", "-pbkdf2",
        "-pass", f"pass:{recovered_password}",
        "-in", secret_path
    ]
    proc = subprocess.run(dec_cmd, capture_output=True, text=True, check=True)
    secret_text = proc.stdout
    print("\n[+] DECRYPTED SECRET MESSAGE:")
    print(secret_text)

    flag_match = re.search(r"p4dCTF\{[^}]+\}", secret_text)
    assert flag_match, "Flag not found in secret message!"
    flag = flag_match.group(0)
    print(f"\n[***] RECOVERED FLAG: {flag} [***]")

    # Close connection
    s.sendall(b"4\n")
    s.close()
    print("VERIFICATION COMPLETE: Exploit succeeded and retrieved flag!")

if __name__ == "__main__":
    main()

