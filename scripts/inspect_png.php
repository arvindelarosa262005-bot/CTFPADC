<?php
$file = '/var/www/html/stego_lsb.png';
$data = file_get_contents($file);

$offset = 8;
$idatData = '';
while ($offset < strlen($data)) {
    $len = unpack('N', substr($data, $offset, 4))[1];
    $type = substr($data, $offset + 4, 4);
    $chunkData = substr($data, $offset + 8, $len);
    $offset += 12 + $len;
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

$unfiltered = '';
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
            default: die("Unknown filter: $filterType\n");
        }
        $currRow[$i] = $recon;
        $unfiltered .= chr($recon);
    }
    $prevRow = $currRow;
}

echo "Unfiltered length: " . strlen($unfiltered) . "\n";

// Now extract LSB from reconstructed pixels!
function searchBits($bits, $label) {
    $text1 = '';
    for ($i = 0; $i < strlen($bits) - 8; $i += 8) {
        $text1 .= chr(bindec(substr($bits, $i, 8)));
    }
    if (strpos($text1, 'p4dCTF') !== false) {
        echo "[$label] Found flag (MSB first)!\n";
        preg_match('/p4dCTF\{[^}]+\}/', $text1, $m);
        echo "FLAG: " . $m[0] . "\n";
        return true;
    }
    $text2 = '';
    for ($i = 0; $i < strlen($bits) - 8; $i += 8) {
        $byteBits = strrev(substr($bits, $i, 8));
        $text2 .= chr(bindec($byteBits));
    }
    if (strpos($text2, 'p4dCTF') !== false) {
        echo "[$label] Found flag (LSB first)!\n";
        preg_match('/p4dCTF\{[^}]+\}/', $text2, $m);
        echo "FLAG: " . $m[0] . "\n";
        return true;
    }
    return false;
}

$allBits = '';
for ($i = 0; $i < strlen($unfiltered); $i++) {
    $allBits .= ord($unfiltered[$i]) & 1;
}
searchBits($allBits, "All RGB LSB sequential");

// Try Red, Green, Blue
$rBits = ''; $gBits = ''; $bBits = '';
for ($i = 0; $i < strlen($unfiltered); $i += 3) {
    $rBits .= ord($unfiltered[$i]) & 1;
    $gBits .= ord($unfiltered[$i+1]) & 1;
    $bBits .= ord($unfiltered[$i+2]) & 1;
}
searchBits($rBits, "Red channel");
searchBits($gBits, "Green channel");
searchBits($bBits, "Blue channel");

// Print first 50 chars of all bits text
$t = '';
for ($i = 0; $i < min(800, strlen($allBits) - 8); $i += 8) {
    $t .= chr(bindec(substr($allBits, $i, 8)));
}
echo "First 50 chars ASCII: " . substr($t, 0, 50) . "\n";

