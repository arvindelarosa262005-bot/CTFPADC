#!/usr/bin/env python3
"""
Generate a genuine synthetic 64-bit ELF Core Dump memory artifact for
the P4DC CTF challenge 'Memory Fragments'.
"""

import struct
import base64
import os

TOTAL_SIZE = 65536  # 64 KB memory dump

def create_memory_dump(output_path):
    # Initialize zero-filled buffer
    buf = bytearray(TOTAL_SIZE)

    # 1. ELF Header (64-bit, Little-Endian)
    # e_ident: \x7fELF (4), EI_CLASS=2 (64-bit), EI_DATA=1 (LSB), EI_VERSION=1, EI_OSABI=0 (SYSV), 8 bytes padding
    buf[0:16] = b'\x7fELF\x02\x01\x01\x00' + b'\x00' * 8
    
    # ELF64 header fields:
    # uint16_t e_type = 4 (ET_CORE)
    # uint16_t e_machine = 62 (EM_X86_64)
    # uint32_t e_version = 1
    # uint64_t e_entry = 0
    # uint64_t e_phoff = 64 (immediately after ELF header)
    # uint64_t e_shoff = 0 (no section header table in core)
    # uint32_t e_flags = 0
    # uint16_t e_ehsize = 64
    # uint16_t e_phentsize = 56 (sizeof(Elf64_Phdr))
    # uint16_t e_phnum = 4 (4 program headers)
    # uint16_t e_shentsize = 0
    # uint16_t e_shnum = 0
    # uint16_t e_shstrndx = 0
    elf_hdr = struct.pack(
        '<HHIQQQIHHHHHH',
        4,   # ET_CORE
        62,  # EM_X86_64
        1,   # e_version
        0,   # e_entry
        64,  # e_phoff
        0,   # e_shoff
        0,   # e_flags
        64,  # e_ehsize
        56,  # e_phentsize
        4,   # e_phnum
        0,   # e_shentsize
        0,   # e_shnum
        0    # e_shstrndx
    )
    buf[16:64] = elf_hdr

    # 2. Program Headers (Elf64_Phdr is 56 bytes)
    # Format: <IIQQQQQQ (p_type, p_flags, p_offset, p_vaddr, p_paddr, p_filesz, p_memsz, p_align)
    # Phdr 0: PT_NOTE (offset 0x200, size 0x600)
    # Phdr 1: PT_LOAD (offset 0x1000, vaddr 0x00400000, size 0x3000) - Code & System Logs
    # Phdr 2: PT_LOAD (offset 0x4000, vaddr 0x00600000, size 0x7000) - Heap & Memory Fragments
    # Phdr 3: PT_LOAD (offset 0xb000, vaddr 0x7fffef800000, size 0x5000) - Stack & Environment

    phdrs = [
        # p_type=4 (PT_NOTE), p_flags=4 (R), offset=0x200, vaddr=0, paddr=0, filesz=0x600, memsz=0, align=0
        struct.pack('<IIQQQQQQ', 4, 4, 0x200, 0, 0, 0x600, 0, 0),
        # p_type=1 (PT_LOAD), p_flags=5 (R|X), offset=0x1000, vaddr=0x00400000, paddr=0, filesz=0x3000, memsz=0x3000, align=0x1000
        struct.pack('<IIQQQQQQ', 1, 5, 0x1000, 0x00400000, 0, 0x3000, 0x3000, 0x1000),
        # p_type=1 (PT_LOAD), p_flags=6 (R|W), offset=0x4000, vaddr=0x00600000, paddr=0, filesz=0x7000, memsz=0x7000, align=0x1000
        struct.pack('<IIQQQQQQ', 1, 6, 0x4000, 0x00600000, 0, 0x7000, 0x7000, 0x1000),
        # p_type=1 (PT_LOAD), p_flags=6 (R|W), offset=0xb000, vaddr=0x7fffef800000, paddr=0, filesz=0x5000, memsz=0x5000, align=0x1000
        struct.pack('<IIQQQQQQ', 1, 6, 0xb000, 0x7fffef800000, 0, 0x5000, 0x5000, 0x1000),
    ]
    for i, ph in enumerate(phdrs):
        buf[64 + i * 56 : 64 + (i + 1) * 56] = ph

    # 3. PT_NOTE Segment (starts at 0x200)
    # Note 1: NT_PRPSINFO (type 3)
    # Elf_Note header: namesz=5 ("CORE\0"), descsz=136, type=3
    note1_hdr = struct.pack('<III', 5, 136, 3)
    note1_name = b'CORE\x00\x00\x00\x00'  # 4-byte aligned
    # prpsinfo desc (136 bytes in 64-bit Linux):
    # 0..3: pr_state, pr_sname, pr_zomb, pr_nice
    # 4..7: padding (4)
    # 8..15: pr_flag (8)
    # 16..19: pr_uid (4)
    # 20..23: pr_gid (4)
    # 24..27: pr_pid (4)
    # 28..31: pr_ppid (4)
    # 32..35: pr_pgrp (4)
    # 36..39: pr_sid (4)
    # 40..55: pr_fname[16]
    # 56..135: pr_psargs[80]
    prpsinfo = bytearray(136)
    prpsinfo[0:4] = b'\x00\x52\x00\x00'
    struct.pack_into('<I', prpsinfo, 16, 1001)  # uid
    struct.pack_into('<I', prpsinfo, 20, 1001)  # gid
    struct.pack_into('<I', prpsinfo, 24, 4018)  # pid
    prpsinfo[40:56] = b'worker_dispatch\x00'
    prpsinfo[56:136] = b'/opt/agent/worker_dispatch --worker-id=3 --collect-mode=fragmented\x00'.ljust(80, b'\x00')
    note1 = note1_hdr + note1_name + bytes(prpsinfo)
    buf[0x200 : 0x200 + len(note1)] = note1

    # Note 2: NT_PRSTATUS (type 1)
    note2_hdr = struct.pack('<III', 5, 112, 1)
    note2_name = b'CORE\x00\x00\x00\x00'
    prstatus = bytearray(112)
    struct.pack_into('<I', prstatus, 0, 11)   # si_signo = SIGSEGV (11)
    struct.pack_into('<I', prstatus, 4, 1)    # si_code = SEGV_MAPERR
    struct.pack_into('<I', prstatus, 24, 4018) # pid
    note2 = note2_hdr + note2_name + bytes(prstatus)
    note2_offset = 0x200 + len(note1)
    buf[note2_offset : note2_offset + len(note2)] = note2

    # Update PT_NOTE filesz to exact note bytes
    total_note_len = len(note1) + len(note2)
    struct.pack_into('<Q', buf, 64 + 32, total_note_len)

    # 4. Code & Log Segment (starts at 0x1000)
    log_data = (
        "[    0.000000] Linux version 6.5.0-generic (buildd@glibc-core) #42-P4DC SMP PREEMPT\n"
        "[    0.000000] Command line: BOOT_IMAGE=/vmlinuz-6.5.0 root=UUID=7f82b01c ro quiet splash\n"
        "[  120.401920] audit: type=1400 audit(1761400800.120:42): apparmor=\"STATUS\" operation=\"profile_load\" profile=\"docker-default\"\n"
        "[  340.118201] systemd[1]: Started Service Agent for Memory Integrity Monitoring.\n"
        "[  780.201402] kernel: [sec_audit] Commencing scheduled forensic snapshot of subsystem memory.\n"
        "[  812.448010] worker_dispatch[4018]: [INIT] Starting worker session SEC-TOKEN-9941\n"
        "[  812.448150] worker_dispatch[4018]: [DEBUG] Storing payload segments in process heap pool\n"
        "[  812.448290] worker_dispatch[4018]: [DEBUG] Block allocated at 0x00605100 (len: 48)\n"
        "[  812.448370] worker_dispatch[4018]: [DEBUG] Block allocated at 0x00606800 (len: 48)\n"
        "[  812.448490] worker_dispatch[4018]: [DEBUG] Block allocated at 0x00608200 (len: 48)\n"
        "[  812.448610] worker_dispatch[4018]: [WARN] Worker thread encountered pointer dereference boundary\n"
        "[  812.449120] worker_dispatch[4018]: segfault at 7fff804021a0 ip 00007f3b8c0142b0 sp 00007fffef910020 error 4 in libc.so.6\n"
        "[  812.450012] systemd-coredump[4025]: Process 4018 (worker_dispatch) of user 1001 dumped core.\n"
        "                                        Coredump written to memory_dump.bin\n"
        "                                        Stack frame: fragment_combine() -> dispatch_memory_block() -> main()\n"
        "[  812.450190] audit(1761400812.340:108): comm=\"worker_dispatch\" exe=\"/opt/agent/worker_dispatch\" state=\"SIGSEGV_DUMP_COMPLETE\"\n"
        "[  812.451000] kernel: [core] Coredump complete for pid 4018. Memory pages preserved.\n"
    ).encode('utf-8')
    buf[0x1000 : 0x1000 + len(log_data)] = log_data

    # Add disassembly/symbols string table in code segment
    symbols = (
        "__libc_start_main\x00fragment_combine\x00dispatch_memory_block\x00malloc\x00free\x00"
        "parse_fragment_header\x00assemble_security_token\x00sigaction\x00sys_coredump\x00"
    ).encode('utf-8')
    buf[0x2500 : 0x2500 + len(symbols)] = symbols

    # 5. Heap & Memory Fragments Segment (starts at 0x4000)
    # The flag is: p4dCTF{fr4gm3nts_0f_m3m0ry}
    # Frag 1: "p4dCTF{fr4g" -> base64: "cDRkQ1RGe2ZyNGc="
    # Frag 2: "m3nts_0f_"   -> base64: "bTNudHNfMGZf"
    # Frag 3: "m3m0ry}"     -> base64: "bTNtMHJ5fQ=="

    # Decoy flags and strings placed in heap to simulate real memory noise and distract superficial grep
    decoys = (
        "--- HEAP CHUNK 0x00601200 [ALLOCATED 128 BYTES] ---\n"
        "SESSION_CACHE: auth_token=9a8b1c4e2d3f0a1b5c8e\n"
        "DECOY_FLAG_ALPHA: p4dCTF{f4k3_m3m0ry_fl4g_d0_n0t_subm1t}\n"
        "--- HEAP CHUNK 0x00601800 [ALLOCATED 256 BYTES] ---\n"
        "CACHE_ENTRY: session_id=0x9f182c status=stale\n"
        "DECOY_FLAG_BETA: p4dCTF{wr0ng_pr0c3ss_m3m0ry_tr4p}\n"
        "FALLBACK_HASH: sha256$e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855\n"
    ).encode('utf-8')
    buf[0x4200 : 0x4200 + len(decoys)] = decoys

    # Structured Memory Fragments (each in a simulated memory record)
    # Fragment 1
    frag1_rec = (
        b"[MEMFRAG:PART_1:INDEX_1/3:cDRkQ1RGe2ZyNGc=]\x00\x00"
        b"\x01\x00\x00\x00\x03\x00\x00\x00" # struct fields: id=1, total=3
    )
    buf[0x5100 : 0x5100 + len(frag1_rec)] = frag1_rec

    # Decoy memory buffer between fragments
    decoy_between = (
        b"[MEM_BUFFER_RESERVED:ADDR=0x00602400:FLAGS=0x0012]\x00"
        b"CACHE_LINE_FLUSH: status=OK, cycles=18240, worker=idle\x00"
    )
    buf[0x6000 : 0x6000 + len(decoy_between)] = decoy_between

    # Fragment 2
    frag2_rec = (
        b"[MEMFRAG:PART_2:INDEX_2/3:bTNudHNfMGZf]\x00\x00"
        b"\x02\x00\x00\x00\x03\x00\x00\x00" # struct fields: id=2, total=3
    )
    buf[0x6800 : 0x6800 + len(frag2_rec)] = frag2_rec

    # Another decoy block
    decoy_between_2 = (
        b"[MEM_BUFFER_TRACE:ROUTINE=crypto_worker:CHECKSUM=0xdeadbeef]\x00"
        b"BUFFER_METRICS: processed_bytes=4096, cache_hits=142, state=idle\x00"
    )
    buf[0x7500 : 0x7500 + len(decoy_between_2)] = decoy_between_2

    # Fragment 3
    frag3_rec = (
        b"[MEMFRAG:PART_3:INDEX_3/3:bTNtMHJ5fQ==]\x00\x00"
        b"\x03\x00\x00\x00\x03\x00\x00\x00" # struct fields: id=3, total=3
    )
    buf[0x8200 : 0x8200 + len(frag3_rec)] = frag3_rec

    # 6. Stack & Environment Segment (starts at 0xb000)
    env_vars = (
        "SHELL=/bin/bash\x00"
        "HOSTNAME=sec-forensic-srv01\x00"
        "USER=analyst\x00"
        "PWD=/home/analyst/investigation\x00"
        "HOME=/home/analyst\x00"
        "LANG=en_US.UTF-8\x00"
        "INV_CASE_ID=P4DC-2026-MEMDUMP\x00"
        "AGENT_TARGET=worker_dispatch\x00"
        "LOGNAME=analyst\x00"
        "PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\x00"
        "MAIL=/var/mail/analyst\x00"
        "TERM=xterm-256color\x00"
        "SHLVL=1\x00"
        "_=/opt/agent/worker_dispatch\x00"
    ).encode('utf-8')
    buf[0xb200 : 0xb200 + len(env_vars)] = env_vars

    # Stack trace and pointers
    stack_data = (
        b"\x20\x00\x91\xef\xff\x7f\x00\x00" # saved rbp
        b"\xb0\x42\x01\x8c\x3b\x7f\x00\x00" # saved rip
        b"\x00\xa0\x10\x40\xff\x7f\x00\x00" # arg pointer
        b"STACK_FRAME_0x7fffef910000: fragment_combine (chunks=3)\x00"
    )
    buf[0xd000 : 0xd000 + len(stack_data)] = stack_data

    # Write out artifact file
    os.makedirs(os.path.dirname(os.path.abspath(output_path)), exist_ok=True)
    with open(output_path, 'wb') as f:
        f.write(buf)

    print(f"[+] Successfully generated synthetic memory dump: {output_path}")
    print(f"[+] Total size: {len(buf)} bytes ({len(buf) // 1024} KB)")

if __name__ == '__main__':
    import sys
    out = sys.argv[1] if len(sys.argv) > 1 else 'memory_dump.bin'
    create_memory_dump(out)
