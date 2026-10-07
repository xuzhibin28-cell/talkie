<?php
declare(strict_types=1);

namespace App\Core;

/**
 * View template renderer with layout support.
 */
class View
{
    /**
     * Render a view file with optional data and layout.
     */
    public static function render(string $viewName, array $data = [], ?string $layout = 'main'): void
    {
        $viewsPath = (string)config('paths.views', dirname(__DIR__, 2) . '/views');
        $viewFile = $viewsPath . '/' . str_replace('.', '/', $viewName) . '.php';

        if (!file_exists($viewFile)) {
            Response::error("View file [{$viewName}] not found.", 500);
            return;
        }

        // Extract variables for view scope
        extract($data, EXTR_SKIP);

        // Helper closures available in views
        $e = fn(?string $val) => e($val);
        $url = fn(string $path = '') => url($path);
        $asset = fn(string $path) => asset($path);
        $csrf_field = fn() => csrf_field();

        // Capture view content
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // If layout is specified, wrap content with layout
        if ($layout !== null) {
            $layoutFile = $viewsPath . '/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                ob_start();
                require $layoutFile;
                $content = ob_get_clean();
            }
        }

        Response::html($content);
    }
}
