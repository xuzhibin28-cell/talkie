<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session manager with CSRF token generation and secure session configuration.
 */
class Session
{
    private static bool $started = false;

    /**
     * Start secure session if not already started.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        if (!headers_sent()) {
            $isHttps = (
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            );

            @session_set_cookie_params([
                'lifetime' => 86400 * 7, // 7 days
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            @session_name('TALKIE_SESSID');
        }

        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }

        self::$started = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    /**
     * Get or generate a CSRF token for this session.
     */
    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['_csrf_token'];
    }

    /**
     * Validate a submitted CSRF token.
     */
    public static function validateCsrf(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }
        $sessionToken = self::csrfToken();
        return hash_equals($sessionToken, $token);
    }

    /**
     * Get or create a unique guest peer identifier for this session.
     */
    public static function getPeerId(): string
    {
        self::start();
        if (empty($_SESSION['_peer_id'])) {
            $_SESSION['_peer_id'] = 'peer_' . bin2hex(random_bytes(8));
        }
        return (string)$_SESSION['_peer_id'];
    }
}
