<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$challenge = App\Models\Challenge::firstOrNew(['slug' => 'rsa-oracle']);

$challenge->title = 'rsa_oracle';
$challenge->slug = 'rsa-oracle';
$challenge->category = 'cryptography';
$challenge->difficulty = 'medium';
$challenge->points = 50;
$challenge->target_type = 'internal';
$challenge->target_url = null;
$challenge->is_active = true;
$challenge->flag = 'p4dCTF{m4ll34bl3_rs4_0r4cl3_cpa_d3crypt}';
$challenge->published_at = now()->subMinutes(5);
$challenge->hints = [
    'Cryptography threat models: chosen-plaintext attack.',
    'OpenSSL can decrypt the message using AES-256-CBC.',
    "Examine the oracle's behavior and the relationship between its inputs and outputs.",
    'Consider the security guarantees a sound public-key encryption scheme should provide.',
];
$challenge->description = "Can you abuse the oracle?\nAn attacker intercepted communications between a bank and a fintech company. They obtained an encrypted message and an encrypted password. The bank provides an RSA oracle that encrypts chosen inputs but refuses to decrypt the password directly.\nRecover the password, decrypt the message, and retrieve the flag.";

$challenge->save();

echo "Challenge rsa_oracle published successfully! ID={$challenge->id}\n";

