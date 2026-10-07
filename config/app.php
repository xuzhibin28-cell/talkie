<?php
declare(strict_types=1);

use App\Core\Env;

// Load .env if not yet loaded
if (!Env::isLoaded()) {
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        Env::load($envFile);
    }
}

$iceServersJson = (string)env('ICE_SERVERS', '[{"urls":"stun:stun.l.google.com:19302"},{"urls":"stun:stun1.l.google.com:19302"}]');
$iceServers = json_decode($iceServersJson, true) ?: [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
];

return [
    'name' => (string)env('APP_NAME', 'Walkie Talkie PWA'),
    'env' => (string)env('APP_ENV', 'production'),
    'debug' => (bool)env('APP_DEBUG', false),
    'url' => (string)env('APP_URL', 'http://localhost/talkie'),
    'base_path' => (string)env('APP_BASE_PATH', '/talkie'),

    'signal' => [
        'secret' => (string)env('SIGNAL_SECRET', 'walkie_talkie_default_secret_please_change'),
        'max_peers' => (int)env('SIGNAL_MAX_PEERS', 8),
        'floor_timeout_ms' => (int)env('SIGNAL_FLOOR_TIMEOUT_MS', 30000), // 30 seconds floor limit
        'peer_timeout_ms' => (int)env('SIGNAL_PEER_TIMEOUT_MS', 15000),   // 15s peer inactivity pruning
        'max_body' => (int)env('SIGNAL_MAX_BODY', 32768),                 // 32 KB request body max
        'max_data' => (int)env('SIGNAL_MAX_DATA', 12288),                 // 12 KB signal data max
    ],

    'rate_limit' => [
        'per_minute' => (int)env('RATE_LIMIT_PER_MINUTE', 180),
    ],

    'ice_servers' => $iceServers,

    'paths' => [
        'root' => dirname(__DIR__),
        'app' => dirname(__DIR__) . '/app',
        'config' => dirname(__DIR__) . '/config',
        'views' => dirname(__DIR__) . '/views',
        'public' => dirname(__DIR__) . '/public',
        'storage' => dirname(__DIR__) . '/storage',
        'rooms' => dirname(__DIR__) . '/storage/rooms',
        'logs' => dirname(__DIR__) . '/storage/logs',
        'ratelimit' => dirname(__DIR__) . '/storage/ratelimit',
    ],
];
