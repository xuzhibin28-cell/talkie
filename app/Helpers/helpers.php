<?php
declare(strict_types=1);

use App\Core\Env;
use App\Core\Session;
use App\Core\Response;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;
        if ($config === null) {
            $configPath = dirname(__DIR__, 2) . '/config/app.php';
            $config = file_exists($configPath) ? require $configPath : [];
        }

        $parts = explode('.', $key);
        $current = $config;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return $default;
            }
            $current = $current[$part];
        }

        return $current;
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML output to prevent XSS attacks.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get or generate the current CSRF token.
     */
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate a hidden HTML input containing the CSRF token.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('url')) {
    /**
     * Generate an absolute or base-path prefixed URL.
     */
    function url(string $path = ''): string
    {
        $base = (string)config('base_path', env('APP_BASE_PATH', '/talkie'));
        $base = '/' . trim($base, '/');
        if ($base === '/') {
            $base = '';
        }

        $path = '/' . ltrim($path, '/');
        return ($base . $path) === '' ? '/' : ($base . $path);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate a URL for a static asset with optional cache-busting timestamp.
     */
    function asset(string $path): string
    {
        $cleanPath = ltrim($path, '/');
        $urlPath = url('assets/' . $cleanPath);

        $filePath = dirname(__DIR__, 2) . '/public/assets/' . $cleanPath;
        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            return $urlPath . '?v=' . $mtime;
        }

        return $urlPath;
    }
}
