<?php

// Override ./vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
// Used by `php artisan serve` to add Service-Worker-Allowed header for sw.js
// In production, add this header via Nginx/Apache config instead.

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && $uri !== '/build/sw.js' && file_exists($publicPath.$uri)) {
    return false;
}

if ($uri === '/build/sw.js') {
    header('Service-Worker-Allowed: /');
    header('Content-Type: text/javascript');
    echo file_get_contents(__DIR__.'/public/build/sw.js');
    exit;
}

require_once $publicPath.'/index.php';
