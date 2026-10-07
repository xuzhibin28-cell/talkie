<?php
declare(strict_types=1);

namespace App\Core;

/**
 * File logger for application events and errors.
 */
class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $logDir = (string)config('paths.logs', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $entry = sprintf("[%s] [%s]: %s%s\n", $timestamp, strtoupper($level), $message, $contextStr);

        @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }
}
