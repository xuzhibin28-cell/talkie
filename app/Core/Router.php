<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight HTTP Router supporting route parameters and middleware.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler, array $middleware = []): self
    {
        $this->addRoute('GET', $path, $handler, $middleware);
        return $this;
    }

    public function post(string $path, array|callable $handler, array $middleware = []): self
    {
        $this->addRoute('POST', $path, $handler, $middleware);
        return $this;
    }

    public function any(string $path, array|callable $handler, array $middleware = []): self
    {
        $this->addRoute('ANY', $path, $handler, $middleware);
        return $this;
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        // Convert {param} placeholders to regex
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#u';

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Dispatch the current request.
     */
    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $request->path();

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Filter named string parameters
                $params = array_filter($matches, fn($key) => is_string($key), ARRAY_FILTER_USE_KEY);

                // Run route middleware
                foreach ($route['middleware'] as $mwClass) {
                    $mw = new $mwClass();
                    $proceed = $mw->handle($request);
                    if ($proceed === false) {
                        return;
                    }
                }

                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action($request, $params);
                    return;
                }

                if (is_callable($handler)) {
                    $handler($request, $params);
                    return;
                }
            }
        }

        // 404 Not Found fallback
        if ($request->isAjax()) {
            Response::json(['error' => 'Endpoint not found'], 404);
        } else {
            View::render('errors.404', ['title' => 'Page Not Found'], 'main');
        }
    }
}
