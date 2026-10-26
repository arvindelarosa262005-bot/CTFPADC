<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;

echo "=== SCANNING ALL BLADE FILES FOR ROUTE REFERENCES ===\n";

$routes = collect(Route::getRoutes()->getRoutesByName());
$viewsPath = resource_path('views');

$missingRoutes = [];
$totalRouteCalls = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsPath));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $filePath = $file->getPathname();
    $relative = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    
    // Match route('name', ...) or route("name", ...)
    if (preg_match_all("/route\(\s*['\"]([^'\"]+)['\"]/", $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $totalRouteCalls++;
            $routeName = $m[1];
            if (!Route::has($routeName)) {
                $missingRoutes[] = [
                    'view' => $relative,
                    'route' => $routeName
                ];
                echo " [MISSING ROUTE] In view '{$relative}': Route '{$routeName}' NOT DEFINED\n";
            }
        }
    }
}

echo "\nTotal route() invocations scanned: {$totalRouteCalls}\n";
echo "Total missing routes found: " . count($missingRoutes) . "\n";

echo "\n=== SCANNING ALL BLADE FILES FOR VIEW INCLUDES / EXTENDS ===\n";

$missingViews = [];
$totalViewCalls = 0;

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $filePath = $file->getPathname();
    $relative = str_replace($viewsPath . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    
    // Match @extends('view'), @include('view', ...), @includeIf, @includeWhen, @includeUnless
    if (preg_match_all("/@(extends|include|includeIf|includeWhen|includeUnless)\(\s*['\"]([^'\"]+)['\"]/", $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $totalViewCalls++;
            $directive = $m[1];
            $viewName = $m[2];
            if (!view()->exists($viewName)) {
                $missingViews[] = [
                    'file' => $relative,
                    'directive' => $directive,
                    'view' => $viewName
                ];
                echo " [MISSING VIEW] In '{$relative}': @{$directive}('{$viewName}') VIEW NOT FOUND\n";
            }
        }
    }
}

echo "\nTotal view directives scanned: {$totalViewCalls}\n";
echo "Total missing views found: " . count($missingViews) . "\n";

echo "\n=== SCANNING ALL CONTROLLERS FOR VIEW CALLS ===\n";
$controllersPath = app_path('Http/Controllers');
$ctrlIterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($controllersPath));
$missingCtrlViews = [];

foreach ($ctrlIterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    
    $filePath = $file->getPathname();
    $relative = str_replace($controllersPath . DIRECTORY_SEPARATOR, '', $filePath);
    $content = file_get_contents($filePath);
    
    // Match view('view.name') or View::make('view.name')
    if (preg_match_all("/(?:view|View::make)\(\s*['\"]([^'\"]+)['\"]/", $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $viewName = $m[1];
            if (!view()->exists($viewName)) {
                $missingCtrlViews[] = [
                    'controller' => $relative,
                    'view' => $viewName
                ];
                echo " [MISSING CONTROLLER VIEW] In controller '{$relative}': view('{$viewName}') NOT FOUND\n";
            }
        }
    }
}

echo "Total missing controller views found: " . count($missingCtrlViews) . "\n";

