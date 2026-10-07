<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Sanitizer and validator for user inputs, nicknames and channel names.
 */
class Sanitizer
{
    /**
     * Sanitize and format a user nickname.
     */
    public static function nickname(?string $nickname): ?string
    {
        if ($nickname === null) {
            return null;
        }

        $clean = trim($nickname);
        // Strip HTML tags and control characters
        $clean = strip_tags($clean);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $clean);
        $clean = preg_replace('/\s+/u', ' ', $clean);

        if ($clean === '') {
            return null;
        }

        // Limit to 24 characters
        if (mb_strlen($clean, 'UTF-8') > 24) {
            $clean = mb_substr($clean, 0, 24, 'UTF-8');
        }

        return $clean;
    }

    /**
     * Sanitize and format a channel name.
     */
    public static function channel(?string $channel): ?string
    {
        if ($channel === null) {
            return null;
        }

        $clean = trim($channel);
        $clean = strtolower($clean);
        // Convert spaces to dashes
        $clean = preg_replace('/\s+/u', '-', $clean);
        // Allow only alphanumeric, dashes, underscores
        $clean = preg_replace('/[^a-z0-9\-_]/u', '', $clean);
        $clean = trim($clean, '-_');

        if ($clean === '') {
            return null;
        }

        // Limit to 64 characters
        if (strlen($clean) > 64) {
            $clean = substr($clean, 0, 64);
        }

        return $clean;
    }
}
