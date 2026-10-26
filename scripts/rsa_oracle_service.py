#!/usr/bin/env python3
"""
P4DC CTF - RSA Encryption Oracle Service
Listens on 0.0.0.0:5000
Provides chosen-plaintext encryption and restricted decryption oracle.
"""

import socketserver
import json
import os
import sys

# Find data file
candidates = [
    os.path.join(os.path.dirname(__file__), "../storage/app/rsa_oracle_data.json"),
    "/var/www/html/storage/app/rsa_oracle_data.json",
    "C:/Users/Admin/Documents/PADC CTF/p4dc-scaffold/storage/app/rsa_oracle_data.json"
]

data_file = None
for c in candidates:
    if os.path.exists(c):
        data_file = os.path.abspath(c)
        break

if not data_file:
    print("Error: rsa_oracle_data.json not found!")
    sys.exit(1)

with open(data_file, "r") as f:
    config = json.load(f)

N = int(config["n_hex"], 16)
E = int(config["e"])
C_TARGET = int(config["c_hex"], 16)

# Extract private exponent d from pem or compute
pem_candidates = [
    os.path.join(os.path.dirname(__file__), "../storage/app/rsa_oracle_key.pem"),
    "/var/www/html/storage/app/rsa_oracle_key.pem",
    "C:/Users/Admin/Documents/PADC CTF/p4dc-scaffold/storage/app/rsa_oracle_key.pem"
]
pem_path = None
for p in pem_candidates:
    if os.path.exists(p):
        pem_path = os.path.abspath(p)
        break

# If d is not directly in config, extract from pem or key
if "d_hex" in config:
    D = int(config["d_hex"], 16)
else:
    import subprocess
    proc = subprocess.run(["openssl", "rsa", "-in", pem_path, "-text", "-noout"], capture_output=True, text=True, check=True)
    text = proc.stdout
    start = text.find("privateExponent:") + len("privateExponent:")
    end = text.find("prime1:", start)
    chunk = text[start:end]
    d_hex = "".join(c for c in chunk if c in "0123456789abcdefABCDEF")
    D = int(d_hex, 16)

BANNER = f"""======================================================================
              P4DC BANKING GATEWAY - RSA SECURITY ORACLE
======================================================================
Notice: This cryptographic oracle validates transactional payloads and
accepts encryption queries. Master password decryption is restricted.
======================================================================

[PUBLIC PARAMETERS]
Modulus N:
{hex(N)}
Public Exponent e:
{E}

[INTERCEPTED CREDENTIAL ARTIFACT]
Password Ciphertext c:
{hex(C_TARGET)}

Available Operations:
  [1] Encrypt Plaintext (Chosen-Plaintext)
  [2] Decrypt Ciphertext (Restricted Oracle)
  [3] Show Key Parameters
  [4] Exit
======================================================================
"""

class OracleHandler(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(BANNER.encode("utf-8"))
        self.wfile.flush()

        while True:
            try:
                self.wfile.write(b"\nEnter option [1-4]> ")
                self.wfile.flush()
                line = self.rfile.readline()
                if not line:
                    break
                choice = line.decode("utf-8", errors="ignore").strip()

                if choice == "1":
                    self.wfile.write(b"Enter plaintext (hex or ASCII): ")
                    self.wfile.flush()
                    p_line = self.rfile.readline().decode("utf-8", errors="ignore").strip()
                    if not p_line:
                        continue
                    if p_line.startswith("0x") or p_line.startswith("0X"):
                        m = int(p_line, 16)
                    elif all(c in "0123456789abcdefABCDEF" for c in p_line) and len(p_line) % 2 == 0:
                        m = int(p_line, 16)
                    else:
                        m = int.from_bytes(p_line.encode("utf-8"), "big")

                    if m >= N:
                        self.wfile.write(b"[-] Error: Message integer must be less than modulus N.\n")
                    else:
                        c_res = pow(m, E, N)
                        self.wfile.write(f"[+] Encrypted Ciphertext:\n{hex(c_res)}\n".encode("utf-8"))

                elif choice == "2":
                    self.wfile.write(b"Enter ciphertext to decrypt (hex): ")
                    self.wfile.flush()
                    c_line = self.rfile.readline().decode("utf-8", errors="ignore").strip()
                    if not c_line:
                        continue
                    if c_line.startswith("0x") or c_line.startswith("0X"):
                        c_val = int(c_line, 16)
                    else:
                        c_val = int(c_line, 16)

                    # Security Policy Check: cannot decrypt the target ciphertext directly
                    if (c_val % N) == (C_TARGET % N):
                        self.wfile.write(b"[!] SECURITY POLICY VIOLATION: Access Denied. Cannot decrypt master password ciphertext directly!\n")
                    else:
                        m_res = pow(c_val, D, N)
                        self.wfile.write(f"[+] Decrypted Plaintext (hex):\n{hex(m_res)}\n".encode("utf-8"))
                        try:
                            # Try to show ASCII if printable
                            raw_bytes = m_res.to_bytes((m_res.bit_length() + 7) // 8, "big")
                            if all(32 <= b <= 126 for b in raw_bytes):
                                self.wfile.write(f"[+] Decrypted Plaintext (ASCII): {raw_bytes.decode('utf-8')}\n".encode("utf-8"))
                        except Exception:
                            pass

                elif choice == "3":
                    self.wfile.write(f"Modulus N: {hex(N)}\nExponent e: {E}\nTarget c: {hex(C_TARGET)}\n".encode("utf-8"))

                elif choice == "4":
                    self.wfile.write(b"Terminating session. Goodbye.\n")
                    self.wfile.flush()
                    break
                else:
                    self.wfile.write(b"[-] Invalid option. Please choose 1, 2, 3, or 4.\n")

            except (ConnectionResetError, ConnectionAbortedError, BrokenPipeError):
                break
            except Exception as e:
                try:
                    self.wfile.write(f"[-] Error: {str(e)}\n".encode("utf-8"))
                    self.wfile.flush()
                except Exception:
                    break

class ThreadedTCPServer(socketserver.ThreadingMixIn, socketserver.TCPServer):
    allow_reuse_address = True

if __name__ == "__main__":
    HOST = "0.0.0.0"
    PORT = 5000
    print(f"Starting RSA Oracle Service on {HOST}:{PORT}...")
    server = ThreadedTCPServer((HOST, PORT), OracleHandler)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\nStopping server.")
        server.shutdown()

