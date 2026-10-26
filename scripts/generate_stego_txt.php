<?php
$pngPath = '/var/www/html/stego_lsb.png';
$targetPath = '/var/www/html/storage/app/public/challenges/stego_lsb.txt';

if (!file_exists($pngPath)) {
    die("Error: $pngPath does not exist\n");
}

$pngData = file_get_contents($pngPath);
echo "PNG size: " . strlen($pngData) . " bytes\n";

$b64 = chunk_split(base64_encode($pngData), 64, "\n");
file_put_contents($targetPath, $b64);
chmod($targetPath, 0644);

echo "stego_lsb.txt written successfully: " . strlen($b64) . " bytes\n";

// Verify
$readBack = file_get_contents($targetPath);
$decoded = base64_decode(trim($readBack));
if ($decoded === $pngData) {
    echo "VERIFICATION PASSED: Decoded bytes match original PNG exactly!\n";
} else {
    echo "VERIFICATION FAILED: Mismatch!\n";
}

