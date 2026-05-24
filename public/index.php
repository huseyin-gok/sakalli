<?php

declare(strict_types=1);

/**
 * Sakallı - Ön uç kontrolcü (front controller)
 * Tüm istekler buradan yönlendirilir.
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Router;

$router = require dirname(__DIR__) . '/routes/web.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Alt dizin kullanımı: SCRIPT_NAME'den base path çıkarımı
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir !== '/' && $scriptDir !== '' && str_starts_with($uri, $scriptDir)) {
    $uri = substr($uri, strlen($scriptDir)) ?: '/';
}

$router->dispatch($method, $uri);
