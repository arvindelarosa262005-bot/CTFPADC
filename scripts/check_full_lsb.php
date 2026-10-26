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
        }
        $currRow[$i] = $recon;
        $unfiltered .= chr($recon);
    }
    $prevRow = $currRow;
}

$allBits = '';
for ($i = 0; $i < strlen($unfiltered); $i++) {
    $allBits .= ord($unfiltered[$i]) & 1;
}

$text = '';
for ($i = 0; $i < strlen($allBits) - 8; $i += 8) {
    $c = chr(bindec(substr($allBits, $i, 8)));
    $text .= $c;
}

// Find any non-zero or non-space characters
$trimmed = trim($text);
echo "Trimmed length: " . strlen($trimmed) . "\n";
echo "First 100 chars: " . bin2hex(substr($text, 0, 100)) . "\n";
echo "ASCII representation:\n" . substr($text, 0, 100) . "\n";

// Check where non-null ends
$lastNonZero = 0;
for ($i = strlen($text) - 1; $i >= 0; $i--) {
    if (ord($text[$i]) !== 0 && $text[$i] !== ' ') {
        $lastNonZero = $i;
        break;
    }
}
echo "Last non-zero/space offset: $lastNonZero\n";
echo "Content up to last non-zero:\n" . substr($text, 0, $lastNonZero + 1) . "\n";

