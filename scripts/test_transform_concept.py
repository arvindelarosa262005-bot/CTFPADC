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
        # Reverse entire string
        res = s3_str[::-1]
    elif mod == 1:
        # Swap halves
        mid = L // 2
        res = s3_str[mid:] + s3_str[:mid]
    else: # mod == 2
        # Circular right shift by 3 (if len >= 3 else reverse)
        if L >= 3:
            shift = 3 % L
            res = s3_str[-shift:] + s3_str[:-shift]
        else:
            res = s3_str[::-1]

    return res

challenge_input = "P4DC{c0gn1t1v3_tr4nsf0rm_v2_2026}"
out = transform(challenge_input)
print("Challenge Input :", challenge_input)
print("Transform Output:", out)
print("ASCII Sum       :", sum(ord(c) for c in challenge_input), "Mod 3:", sum(ord(c) for c in challenge_input) % 3)

