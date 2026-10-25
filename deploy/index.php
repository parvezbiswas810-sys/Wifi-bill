<?php

/**
 * Front controller for a layout where the application lives outside the
 * document root (recommended on cPanel: .env, vendor and storage stay
 * unreachable over HTTP).
 *
 * Adjust $appPath if the application is installed somewhere else.
 */

$appPath = dirname(__DIR__).'/isp-app';

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = $appPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appPath.'/vendor/autoload.php';

$app = require_once $appPath.'/bootstrap/app.php';

/*
 * The application lives outside the document root, so its own public/ is not
 * what the browser sees. Point Laravel's public path at the document root
 * instead: @vite, asset() and the storage symlink then resolve against the
 * files actually being served, and a redeploy that cleans the application
 * directory can no longer take the compiled assets with it.
 */
$app->usePublicPath(__DIR__);

$app->handleRequest(Illuminate\Http\Request::capture());
