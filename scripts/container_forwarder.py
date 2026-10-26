#!/usr/bin/env python3
import socket
import threading
import sys

def forward(source, destination):
    try:
        while True:
            data = source.recv(4096)
            if not data:
                break
            destination.sendall(data)
    except Exception:
        pass
    finally:
        try: source.close()
        except: pass
        try: destination.close()
        except: pass

def main():
    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind(("0.0.0.0", 5000))
    server.listen(20)
    print("Container forwarder listening on 0.0.0.0:5000 -> host.docker.internal:5000")

    while True:
        client_sock, _ = server.accept()
        try:
            remote_sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
            remote_sock.connect(("host.docker.internal", 5000))
            threading.Thread(target=forward, args=(client_sock, remote_sock), daemon=True).start()
            threading.Thread(target=forward, args=(remote_sock, client_sock), daemon=True).start()
        except Exception as e:
            print(f"Forwarding error: {e}")
            client_sock.close()

if __name__ == "__main__":
    main()

