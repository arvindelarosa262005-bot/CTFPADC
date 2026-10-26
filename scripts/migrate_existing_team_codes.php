<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Team;

$teams = Team::all();
$updatedCount = 0;

foreach ($teams as $team) {
    $code = (string) $team->invitation_code_encrypted;
    if (strlen($code) !== 6 || ! ctype_digit($code)) {
        $newCode = Team::generateInvitationCode();
        $team->update([
            'invitation_code_hash' => hash('sha256', $newCode),
            'invitation_code_encrypted' => $newCode,
        ]);
        echo "Updated team '{$team->name}' (ID: {$team->id}) code to 6 digits: {$newCode}\n";
        $updatedCount++;
    } else {
        echo "Team '{$team->name}' (ID: {$team->id}) already has valid 6-digit code: {$code}\n";
    }
}

echo "Completed. Updated $updatedCount team(s).\n";

