<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Encapsulates the current HTTP request.
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $postData;
    private ?array $jsonData = null;
    private array $headers;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->queryParams = $_GET;
        $this->postData = $_POST;
        $this->headers = $this->parseHeaders();
        $this->path = $this->parsePath();
    }

    private function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string)$value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string)$value;
            }
        }
        return $headers;
    }

    private function parsePath(): string
    {
        $uri = parse_url($this->uri, PHP_URL_PATH) ?? '/';

        // Strip base path if configured (e.g. /talkie)
        $base = (string)config('base_path', '/talkie');
        $base = '/' . trim($base, '/');

        if ($base !== '/' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        // Strip index.php if present
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '//' ? '/' : $uri;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postData;
        }
        return $this->postData[$key] ?? $default;
    }

    public function input(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->json();
        if ($data !== null) {
            if ($key === null) {
                return $data;
            }
            return $data[$key] ?? $default;
        }

        if ($key === null) {
            return array_merge($this->queryParams, $this->postData);
        }

        return $this->postData[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function json(): ?array
    {
        if ($this->jsonData !== null) {
            return $this->jsonData;
        }

        $contentType = $this->header('content-type', '');
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $maxBody = (int)config('signal.max_body', 32768);

            if (strlen($raw) > $maxBody) {
                $this->jsonData = null;
                return null;
            }

            $decoded = json_decode($raw, true);
            $this->jsonData = is_array($decoded) ? $decoded : null;
            return $this->jsonData;
        }

        return null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $lower = strtolower($name);
        return $this->headers[$lower] ?? $default;
    }

    public function clientIp(): string
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', (string)$_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '')) === 'xmlhttprequest' ||
               str_contains($this->header('accept', ''), 'application/json');
    }
}
