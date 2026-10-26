<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$challenge = App\Models\Challenge::where('slug', 'hidden-secrets')->first();
$user = App\Models\User::where('role', 'contestant')->first();

$req = Illuminate\Http\Request::create('/challenges/hidden-secrets', 'GET', [], [], [], [
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'HTTP_ACCEPT' => 'text/html',
]);
$app->instance('request', $req);
$session = app('session')->driver();
$session->start();
$req->setLaravelSession($session);
auth()->guard('web')->setUser($user);

$res = $kernel->handle($req);
echo "Status: " . $res->getStatusCode() . "\n";
echo "Content length: " . strlen($res->getContent()) . "\n";
echo $res->getContent() . "\n";
