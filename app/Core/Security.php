<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Security and cryptography utilities.
 */
class Security
{
    /**
     * Create an HMAC-SHA256 signed JWT-like token.
     */
    public static function createToken(array $payload, int $ttlSeconds = 86400): string
    {
        $secret = (string)config('signal.secret', 'default_talkie_secret');
        $payload['iat'] = time();
        $payload['exp'] = time() + $ttlSeconds;

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $base64Payload = rtrim(strtr(base64_encode($jsonPayload), '+/', '-_'), '=');

        $signature = hash_hmac('sha256', $base64Payload, $secret, true);
        $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $base64Payload . '.' . $base64Signature;
    }

    /**
     * Verify and decode an HMAC-SHA256 signed token.
     */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$base64Payload, $base64Signature] = $parts;
        $secret = (string)config('signal.secret', 'default_talkie_secret');

        $expectedSig = hash_hmac('sha256', $base64Payload, $secret, true);
        $expectedBase64Sig = rtrim(strtr(base64_encode($expectedSig), '+/', '-_'), '=');

        if (!hash_equals($expectedBase64Sig, $base64Signature)) {
            return null;
        }

        $remainder = strlen($base64Payload) % 4;
        if ($remainder) {
            $base64Payload .= str_repeat('=', 4 - $remainder);
        }
        $json = base64_decode(strtr($base64Payload, '-_', '+/'));
        if ($json === false) {
            return null;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && time() > $payload['exp']) {
            return null;
        }

        return $payload;
    }
}
