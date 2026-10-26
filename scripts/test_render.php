<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\User::where('username', 'admin')->first();
Illuminate\Support\Facades\Auth::login($admin);
view()->share('errors', new \Illuminate\Support\ViewErrorBag());
$controller = app(App\Http\Controllers\Admin\AdminDashboardController::class);
$view = $controller->index();
$html = $view->render();
echo "Dashboard rendered successfully! HTML length: " . strlen($html) . " bytes\n";
