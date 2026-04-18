<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';

    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }

    session_save_path($sessionPath);
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

    if (file_exists($file)) {
        require $file;
    }
});

function app_base_url(): string
{
    static $baseUrl;

    if ($baseUrl !== null) {
        return $baseUrl;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $directory = rtrim(str_replace('/index.php', '', $scriptName), '/');

    $baseUrl = $directory === '/' ? '' : $directory;

    return $baseUrl;
}

function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');

    if ($path === '//') {
        $path = '/';
    }

    return app_base_url() . $path;
}
