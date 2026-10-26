import urllib.request
import os
from PIL import Image

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.dirname(SCRIPT_DIR)
JPG_PATH = os.path.join(SCRIPT_DIR, "source_dog.jpg")
PNG_PATH = os.path.join(PROJECT_ROOT, "storage", "app", "public", "challenges", "popular_dog_breeds.png")

URL = "https://ezydog.com.au/cdn/shop/articles/popular-dog-breeds_c342eb79-f021-462e-87af-63220c9e7464.jpg?crop=center&height=1200&v=1775637053&width=1200"

print(f"Downloading from {URL}...")
req = urllib.request.Request(URL, headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
with urllib.request.urlopen(req) as resp:
    data = resp.read()

with open(JPG_PATH, "wb") as f:
    f.write(data)

print(f"Downloaded {len(data)} bytes to {JPG_PATH}")

img = Image.open(JPG_PATH)
print(f"Image format: {img.format}, size: {img.size}, mode: {img.mode}")

