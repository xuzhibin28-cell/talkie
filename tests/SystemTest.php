<?php
declare(strict_types=1);

/**
 * End-to-End System Test Suite for Walkie-Talkie Communication Engine
 * Tests Room lifecycle, atomic PTT floor contention, signaling event sequences, and cleanup.
 */

// Autoloader
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) require $file;
});

require_once dirname(__DIR__) . '/app/Helpers/helpers.php';

echo "=== STARTING WALKIE-TALKIE SYSTEM TESTS ===\n\n";

$roomService = new \App\Services\RoomService();
$testChannel = 'test-channel-' . time();
$peer1 = 'peer_user_a_' . bin2hex(random_bytes(4));
$peer2 = 'peer_user_b_' . bin2hex(random_bytes(4));
$nick1 = 'Student Alpha';
$nick2 = 'Student Bravo';

function assertTest(bool $condition, string $testName): void {
    if ($condition) {
        echo " [PASS] {$testName}\n";
    } else {
        echo " [FAIL] {$testName}\n";
        exit(1);
    }
}

// -------------------------------------------------------------
// Test 1: Single user joins channel
// -------------------------------------------------------------
$res1 = $roomService->joinRoom($testChannel, $peer1, $nick1);
assertTest($res1['success'] === true, 'Test 1: User A joins room successfully');
assertTest(count($res1['peers']) === 1, 'Test 1: Room has exactly 1 peer');
assertTest($res1['peers'][0]['nickname'] === $nick1, 'Test 1: Peer nickname is correct');
assertTest($res1['speaker_id'] === null, 'Test 1: Channel floor is initially free');

// -------------------------------------------------------------
// Test 2: Second user joins channel
// -------------------------------------------------------------
$res2 = $roomService->joinRoom($testChannel, $peer2, $nick2);
assertTest($res2['success'] === true, 'Test 2: User B joins room successfully');
assertTest(count($res2['peers']) === 2, 'Test 2: Room now has 2 peers');

// Check User A polls and receives peer_joined event
$pollA = $roomService->poll($testChannel, $peer1, $res1['seq']);
$joinedEvents = array_filter($pollA['events'], fn($e) => $e['type'] === 'peer_joined' && $e['data']['peer_id'] === $peer2);
assertTest(count($joinedEvents) > 0, 'Test 2: User A poll receives peer_joined event for User B');

// -------------------------------------------------------------
// Test 3: User A requests PTT floor
// -------------------------------------------------------------
$pttA = $roomService->requestPtt($testChannel, $peer1);
assertTest($pttA['success'] === true && $pttA['granted'] === true, 'Test 3: User A granted PTT floor');
assertTest($pttA['nickname'] === $nick1, 'Test 3: Speaker nickname matches User A');

// User B polls and sees User A speaking
$pollB = $roomService->poll($testChannel, $peer2, $res2['seq']);
assertTest($pollB['speaker_id'] === $peer1, 'Test 3: User B sees speaker_id is User A');
$grantedEvents = array_filter($pollB['events'], fn($e) => $e['type'] === 'ptt_granted' && $e['data']['peer_id'] === $peer1);
assertTest(count($grantedEvents) > 0, 'Test 3: User B receives ptt_granted event for User A');

// -------------------------------------------------------------
// Test 4: User B presses PTT while User A is speaking (Floor Contention)
// -------------------------------------------------------------
$pttB = $roomService->requestPtt($testChannel, $peer2);
assertTest($pttB['success'] === true && $pttB['granted'] === false, 'Test 4: User B denied PTT while User A is speaking');
assertTest($pttB['speaker_id'] === $peer1, 'Test 4: Response indicates User A holds floor');

// -------------------------------------------------------------
// Test 5: User A transmits WebRTC signal to User B
// -------------------------------------------------------------
$sigData = ['type' => 'offer', 'sdp' => ['type' => 'offer', 'sdp' => 'v=0 audio test']];
$sigRes = $roomService->forwardSignal($testChannel, $peer1, $peer2, $sigData);
assertTest($sigRes['success'] === true, 'Test 5: User A forwards WebRTC SDP offer');

$pollB2 = $roomService->poll($testChannel, $peer2, $pollB['seq']);
$sigEvents = array_filter($pollB2['events'], fn($e) => $e['type'] === 'webrtc_signal' && $e['data']['from_peer_id'] === $peer1);
assertTest(count($sigEvents) > 0, 'Test 5: User B poll receives WebRTC offer');

// -------------------------------------------------------------
// Test 6: User A releases PTT
// -------------------------------------------------------------
$relA = $roomService->releasePtt($testChannel, $peer1);
assertTest($relA['success'] === true && $relA['released'] === true, 'Test 6: User A successfully releases PTT floor');

// -------------------------------------------------------------
// Test 7: User B requests PTT floor after release
// -------------------------------------------------------------
$pttB2 = $roomService->requestPtt($testChannel, $peer2);
assertTest($pttB2['success'] === true && $pttB2['granted'] === true, 'Test 7: User B successfully granted PTT floor');
assertTest($pttB2['peer_id'] === $peer2, 'Test 7: Active speaker is now User B');

// -------------------------------------------------------------
// Test 8: User B leaves channel
// -------------------------------------------------------------
$leaveB = $roomService->leaveRoom($testChannel, $peer2);
assertTest($leaveB['success'] === true, 'Test 8: User B leaves channel');

$pollA2 = $roomService->poll($testChannel, $peer1, $pollA['seq']);
$leftEvents = array_filter($pollA2['events'], fn($e) => $e['type'] === 'peer_left' && $e['data']['peer_id'] === $peer2);
assertTest(count($leftEvents) > 0, 'Test 8: User A receives peer_left event for User B');
assertTest($pollA2['speaker_id'] === null, 'Test 8: Leaving user automatically releases speaking floor');

// -------------------------------------------------------------
// Test 9: Security HMAC Token signing & validation
// -------------------------------------------------------------
$token = \App\Core\Security::createToken(['peer_id' => $peer1, 'channel' => $testChannel, 'nickname' => $nick1], 3600);
$verified = \App\Core\Security::verifyToken($token);
assertTest($verified !== null && $verified['peer_id'] === $peer1, 'Test 9: Valid HMAC-SHA256 token verified');

$tamperedToken = $token . 'tampered';
$tamperedVerified = \App\Core\Security::verifyToken($tamperedToken);
assertTest($tamperedVerified === null, 'Test 9: Tampered HMAC token correctly rejected');

// -------------------------------------------------------------
// Test 10: Room Full Capacity enforcement (Max Peers)
// -------------------------------------------------------------
$capChannel = 'capacity-test-' . time();
$maxPeers = (int)config('signal.max_peers', 8);
for ($i = 0; $i < $maxPeers; $i++) {
    $jRes = $roomService->joinRoom($capChannel, "peer_cap_{$i}", "User {$i}");
    assertTest($jRes['success'] === true, "Test 10: Peer {$i} joins capacity test room");
}

// 9th peer attempts to join
$overflowRes = $roomService->joinRoom($capChannel, "peer_cap_overflow", "Overflow User");
assertTest($overflowRes['success'] === false && !empty($overflowRes['is_full']), 'Test 10: Room rejects extra peer when capacity is full');

// Cleanup test files
@unlink(config('paths.rooms') . '/' . md5(strtolower($testChannel)) . '.json');
@unlink(config('paths.rooms') . '/' . md5(strtolower($capChannel)) . '.json');

echo "\n=== ALL 10 TESTS PASSED SUCCESSFULLY! ===\n";
