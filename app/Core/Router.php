<?php

namespace App\Core;

use RuntimeException;

class Router
{
    /** @var array<string, array<string, callable|array{0: class-string, 1: string}>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function get(string $path, callable|array $handler): void
    {
        $this->register('GET', $path, $handler);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    public function post(string $path, callable|array $handler): void
    {
        $this->register('POST', $path, $handler);
    }

    public function dispatch(?string $requestUri = null, ?string $requestMethod = null): void
    {
        $method = strtoupper($requestMethod ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $lookupMethod = $method === 'HEAD' ? 'GET' : $method;
        $path = $this->normalizePath($requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/'));

        $handler = $this->routes[$lookupMethod][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            View::render('errors/404', ['currentPath' => $path], 'layouts/main');
            return;
        }

        $this->executeHandler($handler, $path);
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function register(string $method, string $path, callable|array $handler): void
    {
        $normalizedPath = $path === '/' ? '/' : '/' . trim($path, '/');
        $this->routes[$method][$normalizedPath] = $handler;
    }

    private function normalizePath(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';

        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        $path = '/' . trim($path, '/');

        if ($path === '//' || $path === '/index.php') {
            return '/';
        }

        return $path;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $handler
     */
    private function executeHandler(callable|array $handler, string $path): void
    {
        if (is_callable($handler)) {
            $handler();
            return;
        }

        [$controllerClass, $method] = $handler;

        if (!class_exists($controllerClass)) {
            throw new RuntimeException(sprintf('Controlador no encontrado: %s', $controllerClass));
        }

        $controller = new $controllerClass();

        if (!method_exists($controller, $method)) {
            throw new RuntimeException(sprintf('Metodo %s no definido en %s', $method, $controllerClass));
        }

        $controller->{$method}($path);
    }
}
