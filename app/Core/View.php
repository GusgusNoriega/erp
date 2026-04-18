<?php

namespace App\Core;

use RuntimeException;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): void
    {
        $viewsPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR;
        $viewFile = $viewsPath . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException(sprintf('Vista no encontrada: %s', $viewFile));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = $viewsPath . str_replace('/', DIRECTORY_SEPARATOR, $layout) . '.php';

        if (!file_exists($layoutFile)) {
            throw new RuntimeException(sprintf('Layout no encontrado: %s', $layoutFile));
        }

        require $layoutFile;
    }
}
