<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * File-based token-bucket rate limiter.
 */
class RateLimiter
{
    public function handle(Request $request): bool
    {
        $limit = (int)config('rate_limit.per_minute', 180);
        if ($limit <= 0) {
            return true;
        }

        $ip = $request->clientIp();
        $rateDir = (string)config('paths.ratelimit', dirname(__DIR__, 2) . '/storage/ratelimit');
        if (!is_dir($rateDir)) {
            @mkdir($rateDir, 0777, true);
        }

        $ipHash = md5($ip);
        $file = $rateDir . '/' . $ipHash . '.json';
        $now = time();

        $fp = @fopen($file, 'c+');
        if (!$fp) {
            return true; // Fail open if file system cannot be locked
        }

        flock($fp, LOCK_EX);

        $size = filesize($file);
        $data = $size > 0 ? json_decode(fread($fp, $size), true) : null;
        if (!is_array($data)) {
            $data = ['window_start' => $now, 'count' => 0];
        }

        // Reset window if 60 seconds elapsed
        if ($now - $data['window_start'] >= 60) {
            $data['window_start'] = $now;
            $data['count'] = 0;
        }

        $data['count']++;

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($data['count'] > $limit) {
            Response::json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please slow down requests.',
                'retry_after' => 60 - ($now - $data['window_start']),
            ], 429);
            return false;
        }

        return true;
    }
}
