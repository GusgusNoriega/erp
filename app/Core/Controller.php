<?php

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        View::render($view, $data, $layout);
    }

    /**
     * @param array<string, scalar|null> $query
     */
    protected function redirect(string $path, array $query = []): never
    {
        $base = url($path);

        if ($query !== []) {
            $base .= '?' . http_build_query($query);
        }

        header('Location: ' . $base);
        exit;
    }
}
