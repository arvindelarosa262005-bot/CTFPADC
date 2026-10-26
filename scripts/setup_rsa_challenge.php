<?php
/**
 * Setup script for rsa_oracle challenge
 */

// Helper to convert hex to decimal string using bcmath
function hex2dec_bc(string $hex): string {
    $dec = '0';
    $len = strlen($hex);
    for ($i = 0; $i < $len; $i++) {
        $digit = hexdec($hex[$i]);
        $dec = bcadd(bcmul($dec, '16'), (string)$digit);
    }
    return $dec;
}

// Helper to convert decimal string to hex string using bcmath
function dec2hex_bc(string $dec): string {
    $hex = '';
    while (bccomp($dec, '0') > 0) {
        $rem = (int)bcmod($dec, '16');
        $hex = dechex($rem) . $hex;
        $dec = bcdiv($dec, '16', 0);
    }
    return $hex ?: '0';
}

$storageDir = __DIR__ . '/../storage/app/public/challenges';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

$keyFile = __DIR__ . '/../storage/app/rsa_oracle_keys.json';

// Generate consistent RSA key pair if not exists
if (file_exists($keyFile)) {
    $keyData = json_decode(file_get_contents($keyFile), true);
    $n_dec = $keyData['n_dec'];
    $e_dec = $keyData['e_dec'];
    $d_dec = $keyData['d_dec'];
    $password = $keyData['password'];
    $flag = $keyData['flag'];
} else {
    // Generate 1024-bit RSA key
    $res = openssl_pkey_new([
        'private_key_bits' => 1024,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $details = openssl_pkey_get_details($res);
    $n_hex = bin2hex($details['rsa']['n']);
    $e_hex = bin2hex($details['rsa']['e']);
    $d_hex = bin2hex($details['rsa']['d']);

    $n_dec = hex2dec_bc($n_hex);
    $e_dec = hex2dec_bc($e_hex);
    $d_dec = hex2dec_bc($d_hex);

    $password = 'FintechAuth2026!BankMasterKey';
    $flag = 'p4dCTF{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}';

    $keyData = [
        'n_dec' => $n_dec,
        'e_dec' => $e_dec,
        'd_dec' => $d_dec,
        'n_hex' => $n_hex,
        'e_hex' => $e_hex,
        'd_hex' => $d_hex,
        'password' => $password,
        'flag' => $flag,
    ];
    file_put_contents($keyFile, json_encode($keyData, JSON_PRETTY_PRINT));
}

echo "RSA Modulus (N bit length): " . strlen(dec2hex_bc($n_dec)) * 4 . " bits\n";

// Compute RSA encryption of password: c = m^e mod N
$passHex = bin2hex($password);
$m_dec = hex2dec_bc($passHex);
$c_dec = bcpowmod($m_dec, $e_dec, $n_dec);
$c_hex = dec2hex_bc($c_dec);

// Store password.enc (Hex representation of ciphertext)
file_put_contents("$storageDir/password.enc", "0x" . $c_hex . "\n");
echo "Written: password.enc (0x$c_hex)\n";

// Create and encrypt secret message using AES-256-CBC and OpenSSL PBKDF2
$secretMessage = <<<EOT
============================================================
P4DC BANKING GATEWAY - INTERBANK SETTLEMENT NOTIFICATION
============================================================
TRANSACTION ID : TX-2026-9844-FINTECH-01
ORIGINATOR     : P4DC CENTRAL SETTLEMENT
RECIPIENT      : FINTECH CORP LIQUIDITY POOL
AMOUNT         : USD 14,850,000.00
AUTH TIMESTAMP : 2026-10-18 01:45:00 UTC
STATUS         : AUTHORIZED & VERIFIED

CLEARANCE FLAG : $flag
============================================================
EOT;

// Generate OpenSSL compatible AES-256-CBC PBKDF2 ciphertext
$salt = openssl_random_pseudo_bytes(8);
$derived = openssl_pbkdf2($password, $salt, 48, 10000, 'sha256');
$key = substr($derived, 0, 32);
$iv = substr($derived, 32, 16);

$ciphertext = openssl_encrypt($secretMessage, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
// Standard OpenSSL file format: "Salted__" (8 bytes) + salt (8 bytes) + ciphertext
$openSslFormat = "Salted__" . $salt . $ciphertext;

file_put_contents("$storageDir/secret.enc", $openSslFormat);
echo "Written: secret.enc (" . strlen($openSslFormat) . " bytes)\n";

// Verification of OpenSSL decryption in PHP
$readSalt = substr($openSslFormat, 8, 8);
$readCipher = substr($openSslFormat, 16);
$verifyDerived = openssl_pbkdf2($password, $readSalt, 48, 10000, 'sha256');
$vKey = substr($verifyDerived, 0, 32);
$vIv = substr($verifyDerived, 32, 16);
$decrypted = openssl_decrypt($readCipher, 'aes-256-cbc', $vKey, OPENSSL_RAW_DATA, $vIv);

if ($decrypted === $secretMessage) {
    echo "VERIFICATION PASSED: OpenSSL AES-256-CBC decryption matches original message!\n";
} else {
    echo "VERIFICATION FAILED: Decryption mismatch!\n";
}

