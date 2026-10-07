<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight .env parser and environment variable loader.
 */
class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from a file.
     */
    public static function load(string $filePath): void
    {
        if (!file_exists($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Split into key and value
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Strip surrounding quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            // Convert boolean / null keywords
            $lower = strtolower($value);
            $parsedValue = match ($lower) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                'empty', '(empty)' => '',
                default => $value,
            };

            self::$variables[$key] = $parsedValue;
            $_ENV[$key] = $parsedValue;
            $_SERVER[$key] = $parsedValue;
            putenv("{$key}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$variables)) {
            return self::$variables[$key];
        }

        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }

        return $default;
    }

    /**
     * Check if env has been loaded.
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}
