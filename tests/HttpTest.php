<?php
declare(strict_types=1);

/**
 * Controller & View Integration Test Script
 */

spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/Helpers/helpers.php';

echo "=== TESTING CONTROLLERS & ENGINE COMPONENTS ===\n\n";

// 1. QrService vector generation
$qrSvg = \App\Services\QrService::renderSvg('http://localhost/talkie', 200);
if (str_starts_with($qrSvg, '<svg') && str_contains($qrSvg, '</svg>')) {
    echo " [PASS] 1. QrService generates clean SVG markup (" . strlen($qrSvg) . " bytes)\n";
} else {
    echo " [FAIL] 1. QrService failed to generate SVG\n";
    exit(1);
}

// 2. Security HMAC Token creation & verification
$peerId = \App\Core\Session::getPeerId();
$token = \App\Core\Security::createToken(['peer_id' => $peerId, 'channel' => 'alpha-squad', 'nickname' => 'TestPilot'], 3600);
$claims = \App\Core\Security::verifyToken($token);

if ($claims !== null && $claims['peer_id'] === $peerId && $claims['nickname'] === 'TestPilot') {
    echo " [PASS] 2. Security HMAC Token generated and verified successfully\n";
} else {
    echo " [FAIL] 2. Security token verification failed\n";
    exit(1);
}

// 3. Room lifecycle & floor control
$roomService = new \App\Services\RoomService();
$joinRes = $roomService->joinRoom('alpha-squad', $peerId, 'TestPilot');
if ($joinRes['success'] === true && count($joinRes['peers']) >= 1) {
    echo " [PASS] 3. RoomService handles join lifecycle (1 peer connected)\n";
} else {
    echo " [FAIL] 3. RoomService join failed\n";
    exit(1);
}

// 4. PTT Request and Release
$pttReq = $roomService->requestPtt('alpha-squad', $peerId);
if ($pttReq['success'] === true && $pttReq['granted'] === true) {
    echo " [PASS] 4. PTT Floor acquisition granted to TestPilot\n";
} else {
    echo " [FAIL] 4. PTT Floor acquisition failed\n";
    exit(1);
}

$pttRel = $roomService->releasePtt('alpha-squad', $peerId);
if ($pttRel['success'] === true && $pttRel['released'] === true) {
    echo " [PASS] 5. PTT Floor release successfully processed\n";
} else {
    echo " [FAIL] 5. PTT Floor release failed\n";
    exit(1);
}

// 5. Input Sanitizer verification
$cleanNick = \App\Helpers\Sanitizer::nickname('  <b>Ghost-Rider 99</b>  ');
if ($cleanNick === 'Ghost-Rider 99') {
    echo " [PASS] 6. Sanitizer cleans nickname and strips HTML tags\n";
} else {
    echo " [FAIL] 6. Sanitizer failed on nickname: '{$cleanNick}'\n";
    exit(1);
}

$cleanChan = \App\Helpers\Sanitizer::channel('Security Team #101!');
if ($cleanChan === 'security-team-101') {
    echo " [PASS] 7. Sanitizer normalizes channel name safely\n";
} else {
    echo " [FAIL] 7. Sanitizer failed on channel: '{$cleanChan}'\n";
    exit(1);
}

// Cleanup room test file
@unlink(config('paths.rooms') . '/' . md5('alpha-squad') . '.json');

echo "\n=== ALL TESTS PASSED SUCCESSFULLY! ===\n";
