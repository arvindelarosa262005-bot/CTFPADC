<?php
$data = file_get_contents('/var/www/html/stego_lsb.png');
// We know from earlier: Top colors:
// [000000] => 79883, [ff0000] => 20, [0000ff] => 19, [00ff00] => 17 ...
// It's a 400x200 black image with a few colored pixels!
echo "Total pixels: " . (400*200) . "\n";
echo "Image data length: " . strlen($data) . "\n";

