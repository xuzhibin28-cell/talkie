<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Security;
use App\Core\View;
use App\Helpers\Sanitizer;
use App\Services\RoomService;

/**
 * Controller for channel room entry and interface.
 */
class ChannelController
{
    /**
     * Handle join form submission.
     */
    public function join(Request $request): void
    {
        $nickname = Sanitizer::nickname((string)$request->post('nickname', ''));
        $channel = Sanitizer::channel((string)$request->post('channel', ''));

        if (!$nickname) {
            Session::flash('error', 'Please enter a valid nickname (1-24 characters).');
            Response::redirect(url('/'));
            return;
        }

        if (!$channel) {
            Session::flash('error', 'Please enter a valid channel name (letters, numbers, hyphens).');
            Response::redirect(url('/'));
            return;
        }

        // Check if room is full before allowing join
        $peerId = Session::getPeerId();
        $roomService = new RoomService();
        $check = $roomService->joinRoom($channel, $peerId, $nickname);

        if (!empty($check['is_full'])) {
            Session::flash('error', $check['error'] ?? 'This channel is currently full.');
            Response::redirect(url('/'));
            return;
        }

        Session::set('nickname', $nickname);
        Session::set('channel', $channel);

        Response::redirect(url('/channel/' . urlencode($channel)));
    }

    /**
     * Display the active Walkie-Talkie channel interface.
     */
    public function show(Request $request, array $params): void
    {
        $rawChannel = $params['name'] ?? '';
        $channel = Sanitizer::channel($rawChannel);

        if (!$channel) {
            Session::flash('error', 'Invalid channel identifier.');
            Response::redirect(url('/'));
            return;
        }

        $nickname = Sanitizer::nickname((string)Session::get('nickname', ''));
        if (!$nickname) {
            // If user directly opened channel URL without nickname, redirect to join with prefilled channel
            Session::set('channel', $channel);
            Session::flash('error', 'Please enter your nickname to enter this channel.');
            Response::redirect(url('/'));
            return;
        }

        $peerId = Session::getPeerId();

        // Create signed HMAC-SHA256 token for signaling
        $token = Security::createToken([
            'peer_id' => $peerId,
            'nickname' => $nickname,
            'channel' => $channel,
        ], 86400); // 24 hours validity

        $iceServers = config('ice_servers', []);
        $floorTimeoutMs = (int)config('signal.floor_timeout_ms', 30000);
        $maxPeers = (int)config('signal.max_peers', 8);

        View::render('channel', [
            'title' => '#' . strtoupper($channel) . ' - Walkie Talkie',
            'channel' => $channel,
            'nickname' => $nickname,
            'peerId' => $peerId,
            'token' => $token,
            'iceServers' => $iceServers,
            'floorTimeoutMs' => $floorTimeoutMs,
            'maxPeers' => $maxPeers,
            'signalUrl' => url('/signal'),
            'leaveUrl' => url('/leave'),
        ], 'main');
    }

    /**
     * Leave channel action.
     */
    public function leave(Request $request): void
    {
        $channel = (string)Session::get('channel', '');
        $peerId = Session::getPeerId();

        if ($channel !== '') {
            $roomService = new RoomService();
            $roomService->leaveRoom($channel, $peerId);
        }

        Session::remove('channel');
        Response::redirect(url('/'));
    }
}
