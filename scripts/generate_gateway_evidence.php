<?php
/**
 * P4DC CTF - Incident Response: Gateway-SRV-04 Challenge Generator
 *
 * Generates three separate evidence files:
 * 1. incident_report.log - SOC timeline and system events
 * 2. evidence_notes.txt  - Investigator triage notes
 * 3. memory_capture.bin  - Genuine binary evidence containing mixed compressed data,
 *                          binary signatures, and the embedded flag in the middle.
 */

$storageDir = __DIR__ . '/../storage/app/public/challenges';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

// ---------------------------------------------------------------------------
// 1. Construct authentic multi-segment binary evidence (memory_capture.bin)
// ---------------------------------------------------------------------------
$targetSize = 8192;

// Front section: Network framing, TLS metadata, gzip block, memory heap structures
$networkHeader = pack('NnNNN', 0x47575356, 0x0100, 0x0A8C160A, 0xC0A86454, 0x00002000); // Magic GWSV, ver, IPs, len
$protoMeta = "\x00\x00\x00\x20STREAM_DESCRIPTOR_BLOCK_4401\x00\x00\x00\x20CIPHER=TLS_AES_256_GCM_SHA384\x00\x00\x00\x10KEY_EXCHANGE=ECDHE_RSA\x00\x00";

// Valid gzip archive with internal gateway routing & service config
$internalConfig = "HOST=gateway-srv-04.p4dc.internal\nLISTEN=10.140.22.10:8443\nPEER=192.168.100.84:48120\nROUTING=INGRESS_PROXY\nTLS_CIPHERS=AES256-GCM-SHA384\nMAX_CONN=2048\nBUFFER_POOL=0x7ffd9820a000\n";
$gzipBlock = gzencode($internalConfig, 9);

// Simulated kernel memory heap and socket buffer structures
$heapBlock = pack('QQQQ', 0x7ffd98200010, 0x0000000000000420, 0x7ffd98200430, 0x0000000000001000);
$heapStrings = "malloc_chunk_t: prev_size=0x0000, size=0x1021, flags=PREV_INUSE\n"
    . "arena: main_arena.top=0x7ffd9820a000\n"
    . "thread_pool_worker_04: epoll_wait(fd=18, events=EPOLLIN|EPOLLOUT, timeout=-1)\n"
    . "ssl_handshake_state: SSL_ST_ACCEPT -> SSL_ST_OK\n";

$frontPayload = $networkHeader . $protoMeta . $gzipBlock . $heapBlock . $heapStrings;

// Pad front half to reach approximately byte 4000 (just before middle)
$targetFrontLength = 4000;
if (strlen($frontPayload) < $targetFrontLength) {
    // Generate deterministic binary noise for front half
    $frontPad = '';
    mt_srand(20261016);
    while (strlen($frontPayload . $frontPad) < $targetFrontLength) {
        $frontPad .= pack('N', mt_rand());
    }
    $frontPayload .= $frontPad;
}

// Middle section: Session remnant and embedded flag
// Recoverable via `strings` or binary analysis
$flagBlock = "\x00\x00\x00\x00[SESSION_STREAM_BUFFER_0x7A40]\x00\x00\x00\x00p4dCTF{strings_r3v34l_s3cr3ts}\x00\x00\x00\x00[PAYLOAD_RECOVERY_COMPLETE]\x00\x00\x00\x00";

// Back section: PNG header fragment, ZIP header fragment, diagnostic records, hex trailers
$pngHeader = pack('C*', 0x89, 0x50, 0x4E, 0x47, 0x0D, 0x0A, 0x1A, 0x0A, 0x00, 0x00, 0x00, 0x0D, 0x49, 0x48, 0x44, 0x52, 0x00, 0x00, 0x01, 0x00, 0x00, 0x00, 0x01, 0x00, 0x08, 0x06, 0x00, 0x00, 0x00);
$zipHeader = pack('C*', 0x50, 0x4B, 0x03, 0x04, 0x14, 0x00, 0x00, 0x00, 0x08, 0x00)
    . pack('V', 0x12345678)
    . "internal_audit.dat";

$backStrings = "socket_opt: SO_RCVBUF=262144, SO_SNDBUF=262144, TCP_NODELAY=1\n"
    . "peer_identity: CN=node-agent-84.infra.p4dc.internal\n"
    . "kernel_ring_buffer: [412.019482] eth0: promiscuous mode disabled\n"
    . "stream_stats: ingress_bytes=8192, packets=6, retrans=0, drop=0\n";

$binaryTrailer = pack('NNNN', 0xDEADBEEF, 0xCAFEBABE, 0xFEEDFACE, 0x01020304);

$backPayload = $pngHeader . $zipHeader . $backStrings . $binaryTrailer;

// Combine and pad to exact target size (8192 bytes)
$binaryPayload = $frontPayload . $flagBlock . $backPayload;
if (strlen($binaryPayload) < $targetSize) {
    mt_srand(20261017);
    $pad = '';
    while (strlen($binaryPayload . $pad) < $targetSize) {
        $pad .= pack('N', mt_rand());
    }
    $binaryPayload .= substr($pad, 0, $targetSize - strlen($binaryPayload));
} elseif (strlen($binaryPayload) > $targetSize) {
    $binaryPayload = substr($binaryPayload, 0, $targetSize);
}

// Compute real SHA256 of memory_capture.bin
$actualSha256 = hash('sha256', $binaryPayload);

file_put_contents("$storageDir/memory_capture.bin", $binaryPayload);
echo "Written: memory_capture.bin (" . strlen($binaryPayload) . " bytes, SHA256: $actualSha256)\n";

// ---------------------------------------------------------------------------
// 2. incident_report.log
// ---------------------------------------------------------------------------
$incidentReport = <<<EOT
================================================================================
P4DC SECURITY OPERATIONS CENTER - INCIDENT INVESTIGATION LOG
INCIDENT ID: IR-2026-0941
TARGET ASSET: GATEWAY-SRV-04.P4DC.INTERNAL (10.140.22.10)
CLASSIFICATION: CONFIDENTIAL // SOC TRIAGE REPORT
EXTRACTION DATE: 2026-10-16 02:22:15 UTC
LEAD ANALYST: IR-OPERATOR-31
STATUS: VOLATILE MEMORY BUFFER DUMPED FOR FORENSIC ANALYSIS
================================================================================

[2026-10-16 02:14:01.010 UTC] [KERNEL] Linux gateway-srv-04 6.8.0-45-generic #45-Ubuntu SMP
[2026-10-16 02:14:01.042 UTC] [SYSTEM] System audit daemon (auditd) active (PID: 812).
[2026-10-16 02:14:01.190 UTC] [KERNEL] Core memory protection flags: ASLR=2, NX=1, SMEP=1, SMAP=1.
[2026-10-16 02:14:02.320 UTC] [NET] Interface eth0: Link is Up - 10Gbps/Full - flow control rx/tx.
[2026-10-16 02:14:02.325 UTC] [NET] IPv4 assigned: 10.140.22.10/24, broadcast 10.140.22.255.
[2026-10-16 02:14:03.011 UTC] [SECURITY] AppArmor enabled; 48 network profiles enforced.
[2026-10-16 02:14:03.490 UTC] [SERVICES] TLS termination proxy daemon (stunnel5) running on port 8443.
[2026-10-16 02:14:05.802 UTC] [AUTH] PAM authentication policy reloaded: strict_token_auth=1.
[2026-10-16 02:14:07.199 UTC] [FIREWALL] iptables filter table loaded (1,280 rules active).
[2026-10-16 02:14:10.450 UTC] [CONN] Inbound TCP connection from 192.168.100.84:48120 accepted.
[2026-10-16 02:14:10.512 UTC] [TLS] Handshake completed: TLSv1.3 / TLS_AES_256_GCM_SHA384.
[2026-10-16 02:14:10.515 UTC] [TLS] Peer certificate subject: CN=node-agent-84.infra.p4dc.internal.
[2026-10-16 02:14:11.890 UTC] [SESSION] Allocated session buffer #4401 at memory address 0x7ffd9820a000.
[2026-10-16 02:14:12.304 UTC] [MONITOR] Heuristic anomaly detector triggered: rule HEUR_SUSPICIOUS_PAYLOAD_LEN.
[2026-10-16 02:14:12.308 UTC] [MONITOR] Payload stream captured for forensic triage; size=8,192 bytes.
[2026-10-16 02:14:13.001 UTC] [ISOLATION] Ingress channel quarantined; routing to memory snapshot cache.
[2026-10-16 02:14:14.220 UTC] [DIAGNOSTIC] Checksum verification: SHA256={$actualSha256}.
[2026-10-16 02:14:15.012 UTC] [DIAGNOSTIC] Shannon entropy score: 5.42 (compressed/encrypted data stream).
[2026-10-16 02:14:16.890 UTC] [STORAGE] Dumping raw packet buffer 0x7ffd9820a000 to evidence file: memory_capture.bin.
[2026-10-16 02:14:17.001 UTC] [KERNEL] Dump completed. Preserving socket descriptor FD=18.
[2026-10-16 02:14:18.440 UTC] [SYSTEM] Primary gateway node quarantined. Failover to GATEWAY-SRV-05 initiated.
[2026-10-16 02:15:00.000 UTC] [EVIDENCE] Memory buffer extracted into raw forensic container 'memory_capture.bin'.
================================================================================
END OF INCIDENT LOG STREAM
================================================================================
EOT;

file_put_contents("$storageDir/incident_report.log", $incidentReport);
echo "Written: incident_report.log (" . strlen($incidentReport) . " bytes)\n";

// ---------------------------------------------------------------------------
// 3. evidence_notes.txt
// ---------------------------------------------------------------------------
$evidenceNotes = <<<EOT
================================================================================
CASE EVIDENCE NOTES - #IR-2026-0941
================================================================================

Case: Gateway Anomaly & Suspicious Buffer Capture
Target Host: GATEWAY-SRV-04 (10.140.22.10)
Evidence Artifacts:
  - incident_report.log (System event timeline and socket transition records)
  - memory_capture.bin  (Extracted raw volatile memory buffer from session #4401)

Examiner Observations:
- Ingress session #4401 triggered an anomalous transfer threshold.
- The volatile memory buffer was dumped directly from kernel memory space before socket teardown.
- The binary payload appears to contain a mixture of structured headers, binary metadata, and unindexed memory segments.
- Initial triage indicates sensitive internal data may have been staged inside the captured stream.

Next Steps:
- Perform forensic inspection of the binary capture.
- Identify all embedded data structures and extract concealed information.
================================================================================
EOT;

file_put_contents("$storageDir/evidence_notes.txt", $evidenceNotes);
echo "Written: evidence_notes.txt (" . strlen($evidenceNotes) . " bytes)\n";

// Update hidden_message.txt with clean investigation evidence without flag
file_put_contents("$storageDir/hidden_message.txt", $incidentReport . "\n\n" . $evidenceNotes . "\n");
echo "Updated: hidden_message.txt\n";
