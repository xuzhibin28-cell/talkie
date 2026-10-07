<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Encapsulates HTTP responses.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private string $content = '';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }
        echo $this->content;
        exit;
    }

    public static function json(mixed $data, int $status = 200): void
    {
        $resp = new self();
        $resp->setStatusCode($status);
        $resp->header('Content-Type', 'application/json; charset=utf-8');
        $resp->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        $resp->setContent(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $resp->send();
    }

    public static function html(string $content, int $status = 200): void
    {
        $resp = new self();
        $resp->setStatusCode($status);
        $resp->header('Content-Type', 'text/html; charset=utf-8');
        $resp->setContent($content);
        $resp->send();
    }

    public static function redirect(string $url, int $status = 302): void
    {
        $resp = new self();
        $resp->setStatusCode($status);
        $resp->header('Location', $url);
        $resp->send();
    }

    public static function error(string $message, int $status = 400): void
    {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
            str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            self::json([
                'success' => false,
                'error' => $message,
            ], $status);
        } else {
            self::html("<h1>HTTP Error {$status}</h1><p>" . htmlspecialchars($message) . "</p>", $status);
        }
    }
}
