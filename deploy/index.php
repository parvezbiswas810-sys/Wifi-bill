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

(require_once $appPath.'/bootstrap/app.php')
    ->handleRequest(Illuminate\Http\Request::capture());
