<?php

declare(strict_types=1);

$port = (int) (getenv('DB_PORT') ?: 3306);

if ($port <= 0) {
    $port = 3306;
}

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => $port,
    'database' => getenv('DB_DATABASE') ?: 'erp',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
    'collation' => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
];

