<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
    $staticPath = __DIR__ . DIRECTORY_SEPARATOR . ltrim($requestPath, '/');

    if ($requestPath !== '/' && is_file($staticPath)) {
        return false;
    }
}

require __DIR__ . '/app/bootstrap.php';

$router = new App\Core\Router();
require __DIR__ . '/config/routes.php';
$router->dispatch();
