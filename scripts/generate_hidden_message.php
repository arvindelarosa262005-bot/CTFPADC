<?php
/**
 * P4DC CTF - Hidden s3cr3ts Forensic Artifact Generator
 *
 * Generates an authentic incident response log and raw memory/network packet buffer artifact.
 * The flag is embedded inside the binary packet dump region, discoverable via `strings`, `hexdump`, or hex editors.
 */

$logLines = [
    "================================================================================",
    "P4DC INCIDENT RESPONSE INVESTIGATION LOG - CASE #IR-2026-9411",
    "TARGET NODE: GATEWAY-SRV-04 (10.140.22.10)",
    "EXTRACTION SOURCE: KERNEL RING BUFFER & NETWORK SOCKET DUMP",
    "INVESTIGATOR ID: SEC-ANALYSIS-UNIT-7",
    "TIMESTAMP: 2026-10-16T02:14:00Z",
    "================================================================================",
    "",
    "[2026-10-16 02:14:01.102] [KERNEL] Linux gateway-srv-04 6.8.0-45-generic #45-Ubuntu SMP",
    "[2026-10-16 02:14:01.144] [AUDIT] Audit subsystem initialized (type=1400, pid=812)",
    "[2026-10-16 02:14:02.320] [NET] Interface eth0: Link is Up - 10Gbps/Full - flow control rx/tx",
    "[2026-10-16 02:14:02.325] [NET] IPv4 address assigned: 10.140.22.10/24 broadcast 10.140.22.255",
    "[2026-10-16 02:14:03.011] [SECURITY] AppArmor enabled; 48 network profiles enforced",
    "[2026-10-16 02:14:03.490] [SERVICES] Starting TLS termination proxy daemon (stunnel5)...",
    "[2026-10-16 02:14:04.112] [SERVICES] stunnel5: 2 services configured; listening on port 8443",
    "[2026-10-16 02:14:05.802] [AUTH] PAM authentication policy reloaded: strict_token_auth=1",
    "[2026-10-16 02:14:07.199] [FIREWALL] iptables filter table loaded (1,280 rules active)",
    "[2026-10-16 02:14:10.450] [CONN] Inbound TCP connection from 192.168.100.84:48120 accepted on port 8443",
    "[2026-10-16 02:14:10.512] [TLS] Handshake completed: TLSv1.3 / TLS_AES_256_GCM_SHA384",
    "[2026-10-16 02:14:10.515] [TLS] Peer certificate subject: CN=node-agent-84.infra.p4dc.internal",
    "[2026-10-16 02:14:11.890] [SESSION] Allocated session buffer #4401 at memory address 0x7ffd9820a000",
    "[2026-10-16 02:14:12.304] [MONITOR] Heuristic anomaly detector triggered: rule HEUR_SUSPICIOUS_PAYLOAD_LEN",
    "[2026-10-16 02:14:12.308] [MONITOR] Payload stream captured for forensic triage; size=2,048 bytes",
    "[2026-10-16 02:14:13.001] [ISOLATION] Ingress channel quarantined; routing to memory snapshot cache",
    "[2026-10-16 02:14:14.220] [DIAGNOSTIC] Checksum verification: MD5=7b5e406f9d3a01a3517865c3b9b47e8c",
    "[2026-10-16 02:14:15.012] [DIAGNOSTIC] Entropy score: 4.82 (mixed text and binary data stream)",
    "[2026-10-16 02:14:16.890] [STORAGE] Dumping raw packet buffer 0x7ffd9820a000 to forensic export stream",
    "[2026-10-16 02:14:17.001] [KERNEL] Dump completed. Preserving socket descriptor FD=18",
    "[2026-10-16 02:14:18.440] [SYSTEM] Normal operation resumed on secondary gateway node",
    "",
    "================================================================================",
    "RAW CAPTURED BUFFER EXTRACTION (OFFSET 0x0000 -> 0x0800)",
    "================================================================================",
];

$textHeader = implode("\n", $logLines) . "\n\n";

// Generate realistic binary payload stream (mimicking network packet / memory dump with headers)
$binaryChunk1 = pack('C*',
    0x1F, 0x8B, 0x08, 0x00, 0x00, 0x00, 0x00, 0x00, 0x02, 0x03, 0xED, 0xBD,
    0x07, 0x60, 0x1C, 0x49, 0x96, 0x25, 0x26, 0x2F, 0x6D, 0xCA, 0x7B, 0x7F,
    0x4E, 0xF0, 0x19, 0xC0, 0xC8, 0xA2, 0x88, 0x88, 0xA0, 0x00, 0x00, 0x00
);

// Internal network payload headers interspersed with binary bytes
$noiseData1 = "\x00\x00\x00\x10STREAM_ID=4401\x00\x00\x00\x1aSRC=192.168.100.84:48120\x00\x00\x00\x10DST=10.140.22.10:8443\x00\x00";
$binaryChunk2 = pack('C*', 0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52);
$noiseData2 = "\x00\x00\x04\x20NODE_CONFIG_CACHE_V2\x00\x00\x02\x10AUTH_SESSION_VALID\x00\x00";

// The embedded forensic flag surrounded by null bytes and binary delimiters
$flagSegment = "\x00\x00\x00\x00[RECOVERED_BUFFER_FRAGMENT]\x00\x00p4dCTF{strings_r3v34l_s3cr3ts}\x00\x00\x00\x00";

// Trailing binary data
$binaryChunk3 = pack('C*',
    0x50, 0x4B, 0x03, 0x04, 0x14, 0x00, 0x08, 0x00, 0x08, 0x00, 0x22, 0x81,
    0xDE, 0xAD, 0xBE, 0xEF, 0xCA, 0xFE, 0xBA, 0xBE, 0x00, 0x00, 0x00, 0x00
) . str_repeat("\x00", 32);

$payload = $textHeader . $binaryChunk1 . $noiseData1 . $binaryChunk2 . $noiseData2 . $flagSegment . $binaryChunk3;

$target = '/var/www/html/storage/app/public/challenges/hidden_message.txt';
file_put_contents($target, $payload);
echo "Generated $target (" . strlen($payload) . " bytes)\n";

