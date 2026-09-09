<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Set SSL certificate paths at runtime for development
ini_set('curl.cainfo', 'C:/php84/php-8.4.25-Win32-vs17-x64/extras/ssl/cacert.pem');
ini_set('openssl.cafile', 'C:/php84/php-8.4.25-Win32-vs17-x64/extras/ssl/cacert.pem');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
