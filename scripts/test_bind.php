<?php
$s = @stream_socket_server('tcp://0.0.0.0:5000', $errno, $errstr);
if ($s) {
    echo "BOUND OK\n";
    fclose($s);
} else {
    echo "BOUND FAIL: $errstr ($errno)\n";
}

