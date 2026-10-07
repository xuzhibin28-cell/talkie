<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Catch any deprecation or warning as exception during test
set_error_handler(function ($severity, $message, $file, $line) {
    echo "ERROR/WARNING/DEPRECATED: [{$severity}] {$message} in {$file}:{$line}\n";
    exit(1);
});

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/Helpers/helpers.php';

// Test QR Service
$svg = \App\Services\QrService::renderSvg('http://192.168.1.100/talkie', 200);

// Test Token & Security
$token = \App\Core\Security::createToken(['peer_id' => 'p1', 'channel' => 'test', 'nickname' => 'Tester'], 3600);
$verified = \App\Core\Security::verifyToken($token);

// Test RoomService
$rs = new \App\Services\RoomService();
$j = $rs->joinRoom('alpha', 'p1', 'Tester');
$p = $rs->requestPtt('alpha', 'p1');
$r = $rs->releasePtt('alpha', 'p1');
$l = $rs->leaveRoom('alpha', 'p1');

// Cleanup
@unlink(config('paths.rooms') . '/' . md5('alpha') . '.json');

echo "ALL STRICT E_ALL CHECKS PASSED WITH ZERO DEPRECATIONS/WARNINGS!\n";
