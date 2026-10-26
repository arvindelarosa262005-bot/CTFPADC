<?php
$png = file_get_contents('/var/www/html/stego_lsb.png');
$b64 = base64_encode($png);

// Test 1: Single line
file_put_contents('/tmp/single.txt', $b64 . "\n");
exec('base64 -d /tmp/single.txt > /tmp/out1.png', $o1, $ret1);
$m1 = (file_get_contents('/tmp/out1.png') === $png);
echo "Single line decoded via Linux base64 -d: " . ($m1 ? "SUCCESS" : "FAILED") . "\n";

// Test 2: 64-char lines
file_put_contents('/tmp/wrapped.txt', chunk_split($b64, 64, "\n"));
exec('base64 -d /tmp/wrapped.txt > /tmp/out2.png', $o2, $ret2);
$m2 = (file_get_contents('/tmp/out2.png') === $png);
echo "64-char wrapped decoded via Linux base64 -d: " . ($m2 ? "SUCCESS" : "FAILED") . "\n";

// Test 3: 76-char lines
file_put_contents('/tmp/wrapped76.txt', chunk_split($b64, 76, "\n"));
exec('base64 -d /tmp/wrapped76.txt > /tmp/out3.png', $o3, $ret3);
$m3 = (file_get_contents('/tmp/out3.png') === $png);
echo "76-char wrapped decoded via Linux base64 -d: " . ($m3 ? "SUCCESS" : "FAILED") . "\n";

