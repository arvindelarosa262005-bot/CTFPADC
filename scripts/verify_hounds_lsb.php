<?php

$path = '/var/www/html/storage/app/public/challenges/popular_dog_breeds.png';
if (!file_exists($path)) {
    die("File not found: $path\n");
}

$data = file_get_contents($path);
echo "PNG file size: " . strlen($data) . " bytes\n";

// Parse PNG
$offset = 8;
$idat = '';
$width = 0;
$height = 0;
$colorType = 0;
$bitDepth = 0;

while ($offset < strlen($data)) {
    $len = unpack('N', substr($data, $offset, 4))[1];
    $type = substr($data, $offset + 4, 4);
    $chunkData = substr($data, $offset + 8, $len);
    $offset += 12 + $len;

    if ($type === 'IHDR') {
        $width = unpack('N', substr($chunkData, 0, 4))[1];
        $height = unpack('N', substr($chunkData, 4, 4))[1];
        $bitDepth = ord($chunkData[8]);
        $colorType = ord($chunkData[9]);
        echo "IHDR: {$width}x{$height}, bitDepth: $bitDepth, colorType: $colorType\n";
    } elseif ($type === 'IDAT') {
        $idat .= $chunkData;
    }
}

$raw = gzuncompress($idat);
echo "Uncompressed scanlines: " . strlen($raw) . " bytes\n";

$bpp = 3; // RGB
$stride = 1 + $width * $bpp;

function paeth($a, $b, $c) {
    $p = $a + $b - $c;
    $pa = abs($p - $a);
    $pb = abs($p - $b);
    $pc = abs($p - $c);
    if ($pa <= $pb && $pa <= $pc) return $a;
    if ($pb <= $pc) return $b;
    return $c;
}

$prevRow = array_fill(0, $width * $bpp, 0);
$bits = [];

for ($y = 0; $y < min($height, 5); $y++) {
    $filter = ord($raw[$y * $stride]);
    $currRow = [];
    for ($i = 0; $i < $width * $bpp; $i++) {
        $x = ord($raw[$y * $stride + 1 + $i]);
        $a = ($i >= $bpp) ? $currRow[$i - $bpp] : 0;
        $b = $prevRow[$i];
        $c = ($i >= $bpp) ? $prevRow[$i - $bpp] : 0;
        switch ($filter) {
            case 0: $val = $x; break;
            case 1: $val = ($x + $a) & 0xFF; break;
            case 2: $val = ($x + $b) & 0xFF; break;
            case 3: $val = ($x + floor(($a + $b) / 2)) & 0xFF; break;
            case 4: $val = ($x + paeth($a, $b, $c)) & 0xFF; break;
            default: $val = $x; break;
        }
        $currRow[$i] = $val;
        if (count($bits) < 512) {
            $bits[] = $val & 1;
        }
    }
    $prevRow = $currRow;
}

$msg = '';
for ($i = 0; $i < count($bits); $i += 8) {
    $byteVal = 0;
    for ($b = 0; $b < 8; $b++) {
        $byteVal = ($byteVal << 1) | $bits[$i + $b];
    }
    if ($byteVal === 0) break;
    $msg .= chr($byteVal);
}

echo "Extracted in PHP: '$msg'\n";
if ($msg === 'p4dCTF{hidden_b3tw33n_p1x3ls}') {
    echo "VERIFIED MATCH!\n";
} else {
    echo "MISMATCH!\n";
}

