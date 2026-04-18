<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$router = new App\Core\Router();
require __DIR__ . '/config/routes.php';
$router->dispatch();
