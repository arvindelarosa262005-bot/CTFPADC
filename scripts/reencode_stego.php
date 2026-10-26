<?php
/**
 * Re-encode stego_lsb.png with p4dCTF{lsb_simple_hidden_flag}
 */
$pngPath = '/var/www/html/stego_lsb.png';
$data = file_get_contents($pngPath);

// Parse PNG chunks
$offset = 8;
$idatData = '';
$ihdrChunk = '';
while ($offset < strlen($data)) {
    $len = unpack('N', substr($data, $offset, 4))[1];
    $type = substr($data, $offset + 4, 4);
    $chunkData = substr($data, $offset + 8, $len);
    $offset += 12 + $len;
    if ($type === 'IHDR') {
        $ihdrChunk = $chunkData;
    }
    if ($type === 'IDAT') {
        $idatData .= $chunkData;
    }
}

$raw = gzuncompress($idatData);
$width = 400;
$height = 200;
$bpp = 3;
$stride = 1 + $width * $bpp;

function paethPredictor($a, $b, $c) {
    $p = $a + $b - $c;
    $pa = abs($p - $a);
    $pb = abs($p - $b);
    $pc = abs($p - $c);
    if ($pa <= $pb && $pa <= $pc) return $a;
    if ($pb <= $pc) return $b;
    return $c;
}

// Unfilter all rows
$pixels = []; // array of flat RGB bytes [0..height*width*3 - 1]
$prevRow = array_fill(0, $width * $bpp, 0);

for ($y = 0; $y < $height; $y++) {
    $filterType = ord($raw[$y * $stride]);
    $currRow = [];
    for ($i = 0; $i < $width * $bpp; $i++) {
        $x = ord($raw[$y * $stride + 1 + $i]);
        $a = ($i >= $bpp) ? $currRow[$i - $bpp] : 0;
        $b = $prevRow[$i];
        $c = ($i >= $bpp) ? $prevRow[$i - $bpp] : 0;
        switch ($filterType) {
            case 0: $recon = $x; break;
            case 1: $recon = ($x + $a) & 0xFF; break;
            case 2: $recon = ($x + $b) & 0xFF; break;
            case 3: $recon = ($x + floor(($a + $b) / 2)) & 0xFF; break;
            case 4: $recon = ($x + paethPredictor($a, $b, $c)) & 0xFF; break;
        }
        $currRow[$i] = $recon;
        $pixels[] = $recon;
    }
    $prevRow = $currRow;
}

echo "Total reconstructed pixel channels: " . count($pixels) . "\n";

// Target flag to embed
$flag = "p4dCTF{lsb_simple_hidden_flag}\x00";
$bits = '';
for ($i = 0; $i < strlen($flag); $i++) {
    $byte = ord($flag[$i]);
    for ($b = 7; $b >= 0; $b--) {
        $bits .= ($byte >> $b) & 1;
    }
}

echo "Bits to embed: " . strlen($bits) . "\n";

// Embed into pixel LSBs
for ($i = 0; $i < strlen($bits); $i++) {
    $bit = (int)$bits[$i];
    $pixels[$i] = ($pixels[$i] & 0xFE) | $bit;
}

// Zero out remaining bits up to 1024 to ensure clean termination
for ($i = strlen($bits); $i < min(count($pixels), strlen($bits) + 512); $i++) {
    $pixels[$i] = ($pixels[$i] & 0xFE);
}

// Re-encode into PNG scanlines with filter type 0 (None) for exact pixel preservation
$newScanlines = '';
for ($y = 0; $y < $height; $y++) {
    $newScanlines .= "\x00"; // filter type 0
    for ($x = 0; $x < $width * $bpp; $x++) {
        $newScanlines .= chr($pixels[$y * $width * $bpp + $x]);
    }
}

// Create new IDAT
$newIdat = gzcompress($newScanlines, 9);

function makePngChunk($type, $data) {
    $len = pack('N', strlen($data));
    $crc = pack('N', crc32($type . $data));
    return $len . $type . $data . $crc;
}

$pngHeader = "\x89PNG\r\n\x1a\n";
$newPng = $pngHeader
    . makePngChunk('IHDR', $ihdrChunk)
    . makePngChunk('IDAT', $newIdat)
    . makePngChunk('IEND', '');

file_put_contents($pngPath, $newPng);
echo "New stego_lsb.png written (" . strlen($newPng) . " bytes)\n";

// Update stego_lsb.txt with Base64
$targetTxt = '/var/www/html/storage/app/public/challenges/stego_lsb.txt';
$b64 = chunk_split(base64_encode($newPng), 64, "\n");
file_put_contents($targetTxt, $b64);
chmod($targetTxt, 0644);
echo "Updated stego_lsb.txt (" . strlen($b64) . " bytes)\n";

// Verification: decode and extract
$verifyBits = '';
$verifyOffset = 8;
$vIdat = '';
while ($verifyOffset < strlen($newPng)) {
    $len = unpack('N', substr($newPng, $verifyOffset, 4))[1];
    $type = substr($newPng, $verifyOffset + 4, 4);
    $cData = substr($newPng, $verifyOffset + 8, $len);
    $verifyOffset += 12 + $len;
    if ($type === 'IDAT') $vIdat .= $cData;
}
$vRaw = gzuncompress($vIdat);
$vStride = 1 + $width * $bpp;
for ($y = 0; $y < $height; $y++) {
    for ($i = 0; $i < $width * $bpp; $i++) {
        $verifyBits .= ord($vRaw[$y * $vStride + 1 + $i]) & 1;
    }
}
$extracted = '';
for ($i = 0; $i < strlen($bits); $i += 8) {
    $extracted .= chr(bindec(substr($verifyBits, $i, 8)));
}
echo "EXTRACTED FLAG FROM VERIFICATION: " . trim($extracted) . "\n";

