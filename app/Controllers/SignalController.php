<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Logger;
use App\Services\RoomService;

/**
 * Controller handling real-time WebRTC HTTP signaling requests.
 */
class SignalController
{
    private RoomService $roomService;

    public function __construct()
    {
        $this->roomService = new RoomService();
    }

    public function handle(Request $request): void
    {
        $payload = $request->json();
        if (!is_array($payload)) {
            Response::json(['success' => false, 'error' => 'Invalid JSON signaling payload.'], 400);
            return;
        }

        $token = (string)($payload['token'] ?? '');
        if ($token === '') {
            Response::json(['success' => false, 'error' => 'Missing authentication token.'], 401);
            return;
        }

        // Validate HMAC-SHA256 Token
        $claims = Security::verifyToken($token);
        if ($claims === null) {
            Response::json(['success' => false, 'error' => 'Invalid or expired session token.'], 401);
            return;
        }

        $channel = (string)($claims['channel'] ?? '');
        $peerId = (string)($claims['peer_id'] ?? '');
        $nickname = (string)($claims['nickname'] ?? 'Guest');
        $type = (string)($payload['type'] ?? '');

        if ($channel === '' || $peerId === '') {
            Response::json(['success' => false, 'error' => 'Malformed token claims.'], 400);
            return;
        }

        try {
            switch ($type) {
                case 'hello':
                    $result = $this->roomService->joinRoom($channel, $peerId, $nickname);
                    Response::json($result, $result['success'] ? 200 : 403);
                    break;

                case 'poll':
                    $lastSeq = (int)($payload['last_seq'] ?? 0);
                    $result = $this->roomService->poll($channel, $peerId, $lastSeq);
                    Response::json($result);
                    break;

                case 'ptt_request':
                    $result = $this->roomService->requestPtt($channel, $peerId);
                    Response::json($result);
                    break;

                case 'ptt_release':
                    $result = $this->roomService->releasePtt($channel, $peerId);
                    Response::json($result);
                    break;

                case 'signal':
                    $toPeerId = isset($payload['to_peer_id']) ? (string)$payload['to_peer_id'] : null;
                    $signalData = (array)($payload['signal'] ?? []);

                    $maxData = (int)config('signal.max_data', 12288);
                    if (strlen(json_encode($signalData)) > $maxData) {
                        Response::json(['success' => false, 'error' => 'Signal payload exceeds allowed size.'], 413);
                        return;
                    }

                    $result = $this->roomService->forwardSignal($channel, $peerId, $toPeerId, $signalData);
                    Response::json($result);
                    break;

                case 'leave':
                    $result = $this->roomService->leaveRoom($channel, $peerId);
                    Response::json($result);
                    break;

                default:
                    Response::json(['success' => false, 'error' => "Unknown signal type [{$type}]."], 400);
                    break;
            }
        } catch (\Throwable $e) {
            Logger::error("Signaling error: " . $e->getMessage(), [
                'type' => $type,
                'channel' => $channel,
                'peer_id' => $peerId,
                'trace' => $e->getTraceAsString(),
            ]);

            Response::json([
                'success' => false,
                'error' => 'Internal signaling server error.',
            ], 500);
        }
    }
}
