import urllib.request
import os
import tempfile
from PIL import Image

TEMP_DIR = tempfile.gettempdir()
JPG_PATH = os.path.join(TEMP_DIR, "source_silent_evidence.jpg")
PNG_PATH = os.path.join(TEMP_DIR, "silent_evidence.png")

URL = "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTzRBIlNLVmA7blVGUiYZqaHLXGbnM2B5uAVXOKa56nLUUKuYJRa__CyCkR&s=10"
FLAG = "p4dCTF{sh4d0ws_h1d3_s3cr3ts}"

print(f"Downloading from {URL}...")
req = urllib.request.Request(URL, headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
with urllib.request.urlopen(req) as resp:
    data = resp.read()

with open(JPG_PATH, "wb") as f:
    f.write(data)

print(f"Downloaded {len(data)} bytes to {JPG_PATH}")

# Open image
img = Image.open(JPG_PATH)
print(f"Original image format: {img.format}, size: {img.size}, mode: {img.mode}")

# Convert to RGB
img_rgb = img.convert("RGB")
width, height = img_rgb.size
print(f"Dimensions: {width} x {height} ({width * height * 3} channel bytes)")

# Convert flag to bits (MSB-first per byte) with null terminator
flag_bytes = FLAG.encode("ascii") + b"\x00"
bits = []
for byte in flag_bytes:
    for b in range(7, -1, -1):
        bits.append((byte >> b) & 1)

print(f"Flag length: {len(FLAG)} chars ({len(bits)} bits to embed)")
if len(bits) > width * height * 3:
    raise ValueError(f"Image too small! Needed {len(bits)} channels, got {width * height * 3}")

# Embed into pixels
pixel_data = list(img_rgb.getdata()) # list of (R, G, B) tuples
new_pixels = []
bit_idx = 0

for r, g, b in pixel_data:
    channels = [r, g, b]
    new_channels = []
    for c in channels:
        if bit_idx < len(bits):
            new_c = (c & ~1) | bits[bit_idx]
            bit_idx += 1
        elif bit_idx < len(bits) + 64: # zero out next 64 bits to ensure clean termination
            new_c = c & ~1
            bit_idx += 1
        else:
            new_c = c
        new_channels.append(new_c)
    new_pixels.append(tuple(new_channels))

# Create new image and save as PNG
stego_img = Image.new("RGB", (width, height))
stego_img.putdata(new_pixels)

stego_img.save(PNG_PATH, format="PNG")
print(f"Saved stego PNG to {PNG_PATH} ({os.path.getsize(PNG_PATH)} bytes)")

# Verification: extract LSBs from the saved PNG
verify_img = Image.open(PNG_PATH)
verify_pixels = list(verify_img.getdata())
extracted_bits = []
for r, g, b in verify_pixels:
    for c in (r, g, b):
        extracted_bits.append(c & 1)

extracted_bytes = bytearray()
for i in range(0, len(extracted_bits) - 7, 8):
    byte_val = 0
    for b in range(8):
        byte_val = (byte_val << 1) | extracted_bits[i + b]
    if byte_val == 0:
        break
    extracted_bytes.append(byte_val)

try:
    extracted_flag = extracted_bytes.decode("ascii")
    print(f"Extracted flag from PNG: {extracted_flag}")
    assert extracted_flag == FLAG, f"Mismatch: expected {FLAG}, got {extracted_flag}"
    print("SUCCESS: Flag extracted and verified perfectly!")
except Exception as e:
    print(f"ERROR: {e}")
    exit(1)

# Verify flag is NOT visible as plaintext in the raw PNG file
with open(PNG_PATH, "rb") as f:
    raw_png = f.read()

assert b"p4dCTF" not in raw_png, "WARNING: Flag was found as plaintext in the PNG file!"
assert b"sh4d0ws" not in raw_png, "WARNING: Flag keyword was found as plaintext in the PNG file!"
print("SUCCESS: Flag is not present in raw file text/metadata.")
print(f"OUTPUT_PNG={PNG_PATH}")
