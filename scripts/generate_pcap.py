import struct
import socket
import time

def checksum(data: bytes) -> int:
    if len(data) % 2 == 1:
        data += b'\x00'
    s = 0
    for i in range(0, len(data), 2):
        w = (data[i] << 8) + data[i + 1]
        s += w
        s = (s & 0xffff) + (s >> 16)
    return ~s & 0xffff

def ip_checksum(header: bytes) -> int:
    return checksum(header)

def tcp_checksum(src_ip: str, dst_ip: str, tcp_header_no_cksum: bytes, payload: bytes) -> int:
    src = socket.inet_aton(src_ip)
    dst = socket.inet_aton(dst_ip)
    length = len(tcp_header_no_cksum) + len(payload)
    pseudo = src + dst + struct.pack('!BBH', 0, 6, length)
    return checksum(pseudo + tcp_header_no_cksum + payload)

def udp_checksum(src_ip: str, dst_ip: str, udp_header_no_cksum: bytes, payload: bytes) -> int:
    src = socket.inet_aton(src_ip)
    dst = socket.inet_aton(dst_ip)
    length = len(udp_header_no_cksum) + len(payload)
    pseudo = src + dst + struct.pack('!BBH', 0, 17, length)
    ck = checksum(pseudo + udp_header_no_cksum + payload)
    return ck if ck != 0 else 0xffff

def icmp_checksum(icmp_header_no_cksum: bytes, payload: bytes) -> int:
    return checksum(icmp_header_no_cksum + payload)

class PcapBuilder:
    def __init__(self):
        self.packets = []
        self.base_time = 1761400000.0 # realistic timestamp
        self.ip_id = 1000

    def add_packet(self, raw_pkt: bytes, delta_sec: float):
        ts = self.base_time + delta_sec
        ts_sec = int(ts)
        ts_usec = int((ts - ts_sec) * 1000000)
        self.packets.append((ts_sec, ts_usec, raw_pkt))

    def build_eth(self, dst_mac: str, src_mac: str, ethertype: int, payload: bytes) -> bytes:
        d_mac = bytes.fromhex(dst_mac.replace(':', ''))
        s_mac = bytes.fromhex(src_mac.replace(':', ''))
        return struct.pack('!6s6sH', d_mac, s_mac, ethertype) + payload

    def build_ipv4(self, src_ip: str, dst_ip: str, proto: int, transport: bytes) -> bytes:
        self.ip_id += 1
        ver_ihl = 0x45
        tos = 0
        total_len = 20 + len(transport)
        flags_offset = 0x4000 # DF
        ttl = 64
        cksum = 0
        hdr = struct.pack('!BBHHHBBH4s4s',
            ver_ihl, tos, total_len, self.ip_id, flags_offset, ttl, proto, cksum,
            socket.inet_aton(src_ip), socket.inet_aton(dst_ip)
        )
        cksum = ip_checksum(hdr)
        hdr = struct.pack('!BBHHHBBH4s4s',
            ver_ihl, tos, total_len, self.ip_id, flags_offset, ttl, proto, cksum,
            socket.inet_aton(src_ip), socket.inet_aton(dst_ip)
        )
        return hdr + transport

    def build_tcp(self, src_ip: str, dst_ip: str, src_port: int, dst_port: int, seq: int, ack: int, flags: int, payload: bytes = b'', window: int = 65535) -> bytes:
        offset_flags = (5 << 12) | flags
        cksum = 0
        hdr = struct.pack('!HHIIHHHH', src_port, dst_port, seq, ack, offset_flags, window, cksum, 0)
        cksum = tcp_checksum(src_ip, dst_ip, hdr, payload)
        hdr = struct.pack('!HHIIHHHH', src_port, dst_port, seq, ack, offset_flags, window, cksum, 0)
        return hdr + payload

    def build_udp(self, src_ip: str, dst_ip: str, src_port: int, dst_port: int, payload: bytes) -> bytes:
        length = 8 + len(payload)
        cksum = 0
        hdr = struct.pack('!HHHH', src_port, dst_port, length, cksum)
        cksum = udp_checksum(src_ip, dst_ip, hdr, payload)
        hdr = struct.pack('!HHHH', src_port, dst_port, length, cksum)
        return hdr + payload

    def build_icmp_echo(self, icmp_type: int, code: int, ident: int, seq: int, payload: bytes) -> bytes:
        cksum = 0
        hdr = struct.pack('!BBHHH', icmp_type, code, cksum, ident, seq)
        cksum = icmp_checksum(hdr, payload)
        hdr = struct.pack('!BBHHH', icmp_type, code, cksum, ident, seq)
        return hdr + payload

    def build_arp(self, op: int, sender_mac: str, sender_ip: str, target_mac: str, target_ip: str) -> bytes:
        s_mac = bytes.fromhex(sender_mac.replace(':', ''))
        t_mac = bytes.fromhex(target_mac.replace(':', ''))
        s_ip = socket.inet_aton(sender_ip)
        t_ip = socket.inet_aton(target_ip)
        return struct.pack('!HHBBH6s4s6s4s', 1, 0x0800, 6, 4, op, s_mac, s_ip, t_mac, t_ip)

    def write_pcap(self, filename: str):
        with open(filename, 'wb') as f:
            # Global Header: magic (0xa1b2c3d4), 2.4, thiszone=0, sigfigs=0, snaplen=65535, linktype=1 (Ethernet)
            f.write(struct.pack('<IHHIIII', 0xa1b2c3d4, 2, 4, 0, 0, 65535, 1))
            for ts_sec, ts_usec, pkt in self.packets:
                f.write(struct.pack('<IIII', ts_sec, ts_usec, len(pkt), len(pkt)))
                f.write(pkt)

def generate():
    builder = PcapBuilder()
    
    # MAC addresses
    CLIENT_MAC = "00:1a:2b:3c:4d:5e"
    GATEWAY_MAC = "00:50:56:c0:00:01"
    SERVER_MAC = "00:50:56:c0:00:50"
    BROADCAST_MAC = "ff:ff:ff:ff:ff:ff"

    # IPs
    CLIENT_IP = "192.168.1.105"
    GATEWAY_IP = "192.168.1.1"
    INT_SERVER_IP = "192.168.1.50"
    TIME_SERVER_IP = "20.189.173.221"
    SSH_SERVER_IP = "192.168.1.20"
    COVERT_SERVER_IP = "198.51.100.42"

    t = 0.0

    # 1. ARP exchange (Who has 192.168.1.1?)
    arp_req = builder.build_arp(1, CLIENT_MAC, CLIENT_IP, "00:00:00:00:00:00", GATEWAY_IP)
    builder.add_packet(builder.build_eth(BROADCAST_MAC, CLIENT_MAC, 0x0806, arp_req), t)
    t += 0.002
    arp_rep = builder.build_arp(2, GATEWAY_MAC, GATEWAY_IP, CLIENT_MAC, CLIENT_IP)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0806, arp_rep), t)
    t += 0.015

    # 2. DNS query & response for internal server
    # Query: api.internal.corp (Transaction ID 0x1a2b)
    dns_query = struct.pack('!HHHHHH', 0x1a2b, 0x0100, 1, 0, 0, 0)
    for part in ["api", "internal", "corp"]:
        dns_query += bytes([len(part)]) + part.encode('ascii')
    dns_query += b'\x00' + struct.pack('!HH', 1, 1) # Type A, Class IN
    udp_dns = builder.build_udp(CLIENT_IP, GATEWAY_IP, 54122, 53, dns_query)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, GATEWAY_IP, 17, udp_dns)), t)
    t += 0.004

    # Response: api.internal.corp -> 192.168.1.50
    dns_resp = struct.pack('!HHHHHH', 0x1a2b, 0x8180, 1, 1, 0, 0)
    for part in ["api", "internal", "corp"]:
        dns_resp += bytes([len(part)]) + part.encode('ascii')
    dns_resp += b'\x00' + struct.pack('!HH', 1, 1)
    # Answer section: name pointer (0xc00c), Type A (1), Class IN (1), TTL 300, len 4, IP
    dns_resp += struct.pack('!HHHIH', 0xc00c, 1, 1, 300, 4) + socket.inet_aton(INT_SERVER_IP)
    udp_dns_rep = builder.build_udp(GATEWAY_IP, CLIENT_IP, 53, 54122, dns_resp)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(GATEWAY_IP, CLIENT_IP, 17, udp_dns_rep)), t)
    t += 0.020

    # 3. Decoy HTTP Traffic (Stream 0: Client -> 192.168.1.50:80)
    # SYN
    syn = builder.build_tcp(CLIENT_IP, INT_SERVER_IP, 51234, 80, seq=10000, ack=0, flags=0x02)
    builder.add_packet(builder.build_eth(SERVER_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, INT_SERVER_IP, 6, syn)), t)
    t += 0.002
    # SYN-ACK
    syn_ack = builder.build_tcp(INT_SERVER_IP, CLIENT_IP, 80, 51234, seq=20000, ack=10001, flags=0x12)
    builder.add_packet(builder.build_eth(CLIENT_MAC, SERVER_MAC, 0x0800, builder.build_ipv4(INT_SERVER_IP, CLIENT_IP, 6, syn_ack)), t)
    t += 0.001
    # ACK
    ack = builder.build_tcp(CLIENT_IP, INT_SERVER_IP, 51234, 80, seq=10001, ack=20001, flags=0x10)
    builder.add_packet(builder.build_eth(SERVER_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, INT_SERVER_IP, 6, ack)), t)
    t += 0.003
    # HTTP Request
    http_req = b"GET /status HTTP/1.1\r\nHost: api.internal.corp\r\nUser-Agent: curl/7.88.1\r\nAccept: */*\r\n\r\n"
    http_req_pkt = builder.build_tcp(CLIENT_IP, INT_SERVER_IP, 51234, 80, seq=10001, ack=20001, flags=0x18, payload=http_req)
    builder.add_packet(builder.build_eth(SERVER_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, INT_SERVER_IP, 6, http_req_pkt)), t)
    t += 0.005
    # HTTP Response
    http_resp = b"HTTP/1.1 200 OK\r\nServer: nginx/1.22.1\r\nContent-Type: application/json\r\nContent-Length: 46\r\nConnection: close\r\n\r\n{\"status\":\"operational\",\"cluster\":\"node-prod-01\"}"
    http_resp_pkt = builder.build_tcp(INT_SERVER_IP, CLIENT_IP, 80, 51234, seq=20001, ack=10001 + len(http_req), flags=0x18, payload=http_resp)
    builder.add_packet(builder.build_eth(CLIENT_MAC, SERVER_MAC, 0x0800, builder.build_ipv4(INT_SERVER_IP, CLIENT_IP, 6, http_resp_pkt)), t)
    t += 0.002
    # Client ACK of response
    ack2 = builder.build_tcp(CLIENT_IP, INT_SERVER_IP, 51234, 80, seq=10001 + len(http_req), ack=20001 + len(http_resp), flags=0x10)
    builder.add_packet(builder.build_eth(SERVER_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, INT_SERVER_IP, 6, ack2)), t)
    t += 0.001
    # Server FIN-ACK
    fin = builder.build_tcp(INT_SERVER_IP, CLIENT_IP, 80, 51234, seq=20001 + len(http_resp), ack=10001 + len(http_req), flags=0x11)
    builder.add_packet(builder.build_eth(CLIENT_MAC, SERVER_MAC, 0x0800, builder.build_ipv4(INT_SERVER_IP, CLIENT_IP, 6, fin)), t)
    t += 0.001
    # Client FIN-ACK
    c_fin = builder.build_tcp(CLIENT_IP, INT_SERVER_IP, 51234, 80, seq=10001 + len(http_req), ack=20001 + len(http_resp) + 1, flags=0x11)
    builder.add_packet(builder.build_eth(SERVER_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, INT_SERVER_IP, 6, c_fin)), t)
    t += 0.001
    # Server final ACK
    s_ack = builder.build_tcp(INT_SERVER_IP, CLIENT_IP, 80, 51234, seq=20001 + len(http_resp) + 1, ack=10001 + len(http_req) + 1, flags=0x10)
    builder.add_packet(builder.build_eth(CLIENT_MAC, SERVER_MAC, 0x0800, builder.build_ipv4(INT_SERVER_IP, CLIENT_IP, 6, s_ack)), t)
    t += 0.050

    # 4. Decoy ICMP Echo (Ping to Gateway)
    for seq_num in [1, 2]:
        icmp_payload = b"abcdefghijklmnopqrstuvwabcdefghi"
        echo_req = builder.build_icmp_echo(8, 0, 0x1234, seq_num, icmp_payload)
        builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, GATEWAY_IP, 1, echo_req)), t)
        t += 0.001
        echo_rep = builder.build_icmp_echo(0, 0, 0x1234, seq_num, icmp_payload)
        builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(GATEWAY_IP, CLIENT_IP, 1, echo_rep)), t)
        t += 0.030

    # 5. Decoy SSH Banner Exchange (Stream 1)
    syn_ssh = builder.build_tcp(CLIENT_IP, SSH_SERVER_IP, 49812, 22, seq=30000, ack=0, flags=0x02)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, SSH_SERVER_IP, 6, syn_ssh)), t)
    t += 0.003
    syn_ack_ssh = builder.build_tcp(SSH_SERVER_IP, CLIENT_IP, 22, 49812, seq=40000, ack=30001, flags=0x12)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(SSH_SERVER_IP, CLIENT_IP, 6, syn_ack_ssh)), t)
    t += 0.001
    ack_ssh = builder.build_tcp(CLIENT_IP, SSH_SERVER_IP, 49812, 22, seq=30001, ack=40001, flags=0x10)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, SSH_SERVER_IP, 6, ack_ssh)), t)
    t += 0.004
    ssh_banner_s = b"SSH-2.0-OpenSSH_8.9p1 Ubuntu-3ubuntu0.10\r\n"
    pkt_ssh_s = builder.build_tcp(SSH_SERVER_IP, CLIENT_IP, 22, 49812, seq=40001, ack=30001, flags=0x18, payload=ssh_banner_s)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(SSH_SERVER_IP, CLIENT_IP, 6, pkt_ssh_s)), t)
    t += 0.002
    ssh_banner_c = b"SSH-2.0-OpenSSH_8.9p1\r\n"
    pkt_ssh_c = builder.build_tcp(CLIENT_IP, SSH_SERVER_IP, 49812, 22, seq=30001, ack=40001 + len(ssh_banner_s), flags=0x18, payload=ssh_banner_c)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, SSH_SERVER_IP, 6, pkt_ssh_c)), t)
    t += 0.002
    # SSH Teardown
    fin_ssh_s = builder.build_tcp(SSH_SERVER_IP, CLIENT_IP, 22, 49812, seq=40001 + len(ssh_banner_s), ack=30001 + len(ssh_banner_c), flags=0x11)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(SSH_SERVER_IP, CLIENT_IP, 6, fin_ssh_s)), t)
    t += 0.001
    fin_ssh_c = builder.build_tcp(CLIENT_IP, SSH_SERVER_IP, 49812, 22, seq=30001 + len(ssh_banner_c), ack=40001 + len(ssh_banner_s) + 1, flags=0x11)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, SSH_SERVER_IP, 6, fin_ssh_c)), t)
    t += 0.001
    ack_ssh_fin = builder.build_tcp(SSH_SERVER_IP, CLIENT_IP, 22, 49812, seq=40001 + len(ssh_banner_s) + 1, ack=30001 + len(ssh_banner_c) + 1, flags=0x10)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(SSH_SERVER_IP, CLIENT_IP, 6, ack_ssh_fin)), t)
    t += 0.080

    # 6. THE SUSPICIOUS TCP CONVERSATION (Stream 2)
    # Port 9001 (custom covert channel) between 192.168.1.105:41829 and 198.51.100.42:9001
    # Flag: p4dCTF{tcp_str34m_r3c0v3r3d}
    c_seq = 50000
    s_seq = 80000

    # Handshake: SYN
    syn_covert = builder.build_tcp(CLIENT_IP, COVERT_SERVER_IP, 41829, 9001, seq=c_seq, ack=0, flags=0x02)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, COVERT_SERVER_IP, 6, syn_covert)), t)
    t += 0.012
    c_seq += 1

    # Handshake: SYN-ACK
    syn_ack_covert = builder.build_tcp(COVERT_SERVER_IP, CLIENT_IP, 9001, 41829, seq=s_seq, ack=c_seq, flags=0x12)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(COVERT_SERVER_IP, CLIENT_IP, 6, syn_ack_covert)), t)
    t += 0.003
    s_seq += 1

    # Handshake: ACK
    ack_covert = builder.build_tcp(CLIENT_IP, COVERT_SERVER_IP, 41829, 9001, seq=c_seq, ack=s_seq, flags=0x10)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, COVERT_SERVER_IP, 6, ack_covert)), t)
    t += 0.005

    # Message 1: Client sends authentication and request
    msg1 = b"AUTH agent_id=0x4a9f nonce=88319\n"
    pkt_msg1 = builder.build_tcp(CLIENT_IP, COVERT_SERVER_IP, 41829, 9001, seq=c_seq, ack=s_seq, flags=0x18, payload=msg1)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, COVERT_SERVER_IP, 6, pkt_msg1)), t)
    t += 0.010
    c_seq += len(msg1)

    # Message 2: Server responds
    msg2 = b"AUTH_OK Welcome, agent. Channel secured. Transmit report.\n"
    pkt_msg2 = builder.build_tcp(COVERT_SERVER_IP, CLIENT_IP, 9001, 41829, seq=s_seq, ack=c_seq, flags=0x18, payload=msg2)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(COVERT_SERVER_IP, CLIENT_IP, 6, pkt_msg2)), t)
    t += 0.015
    s_seq += len(msg2)

    # Message 3: Client delivers payload containing the FLAG
    msg3 = b"STATUS REPORT: Extraction complete. Payload key follows: p4dCTF{tcp_str34m_r3c0v3r3d}\n"
    pkt_msg3 = builder.build_tcp(CLIENT_IP, COVERT_SERVER_IP, 41829, 9001, seq=c_seq, ack=s_seq, flags=0x18, payload=msg3)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, COVERT_SERVER_IP, 6, pkt_msg3)), t)
    t += 0.012
    c_seq += len(msg3)

    # Message 4: Server acknowledges and announces shutdown
    msg4 = b"ACK 200 RECORD_SAVED. Terminating link.\n"
    pkt_msg4 = builder.build_tcp(COVERT_SERVER_IP, CLIENT_IP, 9001, 41829, seq=s_seq, ack=c_seq, flags=0x18, payload=msg4)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(COVERT_SERVER_IP, CLIENT_IP, 6, pkt_msg4)), t)
    t += 0.005
    s_seq += len(msg4)

    # Teardown: Client FIN-ACK
    fin_covert_c = builder.build_tcp(CLIENT_IP, COVERT_SERVER_IP, 41829, 9001, seq=c_seq, ack=s_seq, flags=0x11)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, COVERT_SERVER_IP, 6, fin_covert_c)), t)
    t += 0.002
    c_seq += 1

    # Teardown: Server FIN-ACK
    fin_covert_s = builder.build_tcp(COVERT_SERVER_IP, CLIENT_IP, 9001, 41829, seq=s_seq, ack=c_seq, flags=0x11)
    builder.add_packet(builder.build_eth(CLIENT_MAC, GATEWAY_MAC, 0x0800, builder.build_ipv4(COVERT_SERVER_IP, CLIENT_IP, 6, fin_covert_s)), t)
    t += 0.002
    s_seq += 1

    # Teardown: Client final ACK
    ack_covert_fin = builder.build_tcp(CLIENT_IP, COVERT_SERVER_IP, 41829, 9001, seq=c_seq, ack=s_seq, flags=0x10)
    builder.add_packet(builder.build_eth(GATEWAY_MAC, CLIENT_MAC, 0x0800, builder.build_ipv4(CLIENT_IP, COVERT_SERVER_IP, 6, ack_covert_fin)), t)

    out_path = "storage/app/public/challenges/the_silent_conversation.pcap"
    builder.write_pcap(out_path)
    print(f"Generated {len(builder.packets)} packets in PCAP file: {out_path}")

if __name__ == "__main__":
    generate()
