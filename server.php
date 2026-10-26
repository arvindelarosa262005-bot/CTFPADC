<?php

declare(strict_types=1);

/**
 * PHP built-in development server router.
 *
 * Laravel's ServeCommand prefers this file over the framework
 * default router whenever it exists at the project root.
 *
 * This router uses is_file() instead of file_exists(). The
 * framework default treats any existing path — including
 * directories — as a static asset and serves it directly. That
 * behaviour lets a public asset directory shadow an application
 * route of the same name: for example public/challenges/ (which
 * holds the Source Trail target stylesheet and script) would
 * intercept GET /challenges and GET /challenges/{slug} and return
 * a 404/403 instead of reaching the application.
 *
 * Using is_file() means only real files are served statically.
 * Directory requests fall through to the front controller, so
 * application routes always take precedence over public
 * directories. Static asset files (CSS, JS, images) are still
 * served directly and are unaffected.
 */

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
