<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Room;
use App\Models\Peer;
use App\Core\Logger;

/**
 * Service managing rooms with atomic file locking and floor control.
 */
class RoomService
{
    private string $storageDir;
    private int $maxPeers;
    private int $floorTimeoutMs;
    private int $peerTimeoutMs;

    public function __construct()
    {
        $this->storageDir = (string)config('paths.rooms', dirname(__DIR__, 2) . '/storage/rooms');
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0777, true);
        }

        $this->maxPeers = (int)config('signal.max_peers', 8);
        $this->floorTimeoutMs = (int)config('signal.floor_timeout_ms', 30000);
        $this->peerTimeoutMs = (int)config('signal.peer_timeout_ms', 15000);
    }

    private function getRoomFilePath(string $roomName): string
    {
        $hash = md5(strtolower($roomName));
        return $this->storageDir . '/' . $hash . '.json';
    }

    /**
     * Atomically load, execute a mutation callback, and save the room state.
     *
     * @param string $roomName
     * @param callable(Room): mixed $callback
     * @return mixed Result of callback
     */
    public function withLockedRoom(string $roomName, callable $callback): mixed
    {
        $filePath = $this->getRoomFilePath($roomName);
        $fp = @fopen($filePath, 'c+');

        if (!$fp) {
            Logger::error("Failed to open room file: {$filePath}");
            throw new \RuntimeException("Could not access room storage.");
        }

        // Acquire exclusive lock
        flock($fp, LOCK_EX);

        try {
            $size = filesize($filePath);
            $room = null;

            if ($size > 0) {
                $content = fread($fp, $size);
                $data = json_decode($content, true);
                if (is_array($data)) {
                    $room = Room::fromArray($roomName, $data);
                }
            }

            if ($room === null) {
                $room = new Room($roomName);
            }

            // Clean up stale peers & expired speaker floor
            $this->pruneStaleState($room);

            // Execute the requested operation
            $result = $callback($room);

            // Save state back to file
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($room->toArray(), JSON_UNESCAPED_UNICODE));
            fflush($fp);

            return $result;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Check and clean up inactive peers and timed-out speakers.
     */
    private function pruneStaleState(Room $room): void
    {
        $now = time();
        $nowMs = (int)round(microtime(true) * 1000);

        // Check if current speaker exceeded floor limit (e.g. 30s)
        if ($room->speakerId !== null && $room->speakerStartedAt !== null) {
            if ($nowMs - $room->speakerStartedAt > $this->floorTimeoutMs) {
                $expiredPeerId = $room->speakerId;
                $expiredNick = $room->speakerNickname;
                $room->clearSpeaker();
                $room->addEvent('floor_timeout', [
                    'peer_id' => $expiredPeerId,
                    'nickname' => $expiredNick,
                    'message' => 'Speaker floor time limit reached (30s). Floor released.',
                ]);
            }
        }

        // Check peer inactivity
        $staleThreshold = $this->peerTimeoutMs / 1000;
        foreach ($room->peers as $peerId => $peer) {
            if ($now - $peer->lastSeen > $staleThreshold) {
                $removedPeer = $room->removePeer($peerId);
                if ($removedPeer !== null) {
                    $room->addEvent('peer_left', [
                        'peer_id' => $peerId,
                        'nickname' => $removedPeer->nickname,
                        'reason' => 'timeout',
                    ]);
                }
            }
        }
    }

    /**
     * Join a room. Returns room state and peer list, or throws error if full.
     */
    public function joinRoom(string $roomName, string $peerId, string $nickname): array
    {
        return $this->withLockedRoom($roomName, function (Room $room) use ($peerId, $nickname, $roomName) {
            $existingPeer = $room->getPeer($peerId);

            if (!$existingPeer && count($room->peers) >= $this->maxPeers) {
                return [
                    'success' => false,
                    'error' => "This channel is currently full. Maximum {$this->maxPeers} users are allowed.",
                    'is_full' => true,
                ];
            }

            $now = time();
            $peer = new Peer(
                id: $peerId,
                nickname: $nickname,
                joinedAt: $existingPeer ? $existingPeer->joinedAt : $now,
                lastSeen: $now,
                isSpeaking: ($room->speakerId === $peerId)
            );

            $room->addPeer($peer);

            if (!$existingPeer) {
                $room->addEvent('peer_joined', [
                    'peer_id' => $peerId,
                    'nickname' => $nickname,
                    'joined_at' => $now,
                ]);
            }

            return [
                'success' => true,
                'room' => $room->name,
                'peers' => array_values(array_map(fn(Peer $p) => $p->toArray(), $room->peers)),
                'speaker_id' => $room->speakerId,
                'speaker_nickname' => $room->speakerNickname,
                'speaker_started_at' => $room->speakerStartedAt,
                'seq' => $room->seqCounter,
                'max_peers' => $this->maxPeers,
                'floor_timeout_ms' => $this->floorTimeoutMs,
            ];
        });
    }

    /**
     * Leave a room.
     */
    public function leaveRoom(string $roomName, string $peerId): array
    {
        return $this->withLockedRoom($roomName, function (Room $room) use ($peerId) {
            $peer = $room->removePeer($peerId);
            if ($peer !== null) {
                $room->addEvent('peer_left', [
                    'peer_id' => $peerId,
                    'nickname' => $peer->nickname,
                    'reason' => 'user_left',
                ]);
            }

            return [
                'success' => true,
                'peers_count' => count($room->peers),
            ];
        });
    }

    /**
     * Poll for room events, update peer heartbeat, and retrieve new events.
     */
    public function poll(string $roomName, string $peerId, int $lastSeq): array
    {
        return $this->withLockedRoom($roomName, function (Room $room) use ($peerId, $lastSeq) {
            $peer = $room->getPeer($peerId);
            if ($peer !== null) {
                $peer->lastSeen = time();
            }

            $newEvents = $room->getEventsAfter($lastSeq);

            return [
                'success' => true,
                'events' => $newEvents,
                'seq' => $room->seqCounter,
                'peers' => array_values(array_map(fn(Peer $p) => $p->toArray(), $room->peers)),
                'speaker_id' => $room->speakerId,
                'speaker_nickname' => $room->speakerNickname,
                'speaker_started_at' => $room->speakerStartedAt,
            ];
        });
    }

    /**
     * Request the communication floor (Push-To-Talk).
     */
    public function requestPtt(string $roomName, string $peerId): array
    {
        return $this->withLockedRoom($roomName, function (Room $room) use ($peerId) {
            $peer = $room->getPeer($peerId);
            if ($peer === null) {
                return [
                    'success' => false,
                    'granted' => false,
                    'error' => 'You are not connected to this channel.',
                ];
            }

            $peer->lastSeen = time();

            // Floor is currently held by someone else
            if ($room->speakerId !== null && $room->speakerId !== $peerId) {
                return [
                    'success' => true,
                    'granted' => false,
                    'speaker_id' => $room->speakerId,
                    'speaker_nickname' => $room->speakerNickname,
                    'message' => "Channel busy: {$room->speakerNickname} is currently speaking.",
                ];
            }

            // Floor is free or already held by this user
            $room->setSpeaker($peerId, $peer->nickname);
            $room->addEvent('ptt_granted', [
                'peer_id' => $peerId,
                'nickname' => $peer->nickname,
                'started_at' => $room->speakerStartedAt,
                'floor_timeout_ms' => $this->floorTimeoutMs,
            ]);

            return [
                'success' => true,
                'granted' => true,
                'peer_id' => $peerId,
                'nickname' => $peer->nickname,
                'started_at' => $room->speakerStartedAt,
                'floor_timeout_ms' => $this->floorTimeoutMs,
                'seq' => $room->seqCounter,
            ];
        });
    }

    /**
     * Release the communication floor.
     */
    public function releasePtt(string $roomName, string $peerId): array
    {
        return $this->withLockedRoom($roomName, function (Room $room) use ($peerId) {
            $peer = $room->getPeer($peerId);
            if ($peer !== null) {
                $peer->lastSeen = time();
            }

            if ($room->speakerId === $peerId) {
                $oldSpeakerNick = $room->speakerNickname;
                $room->clearSpeaker();
                $room->addEvent('ptt_released', [
                    'peer_id' => $peerId,
                    'nickname' => $oldSpeakerNick,
                ]);

                return [
                    'success' => true,
                    'released' => true,
                    'seq' => $room->seqCounter,
                ];
            }

            return [
                'success' => true,
                'released' => false,
                'message' => 'Floor was not held by this user.',
            ];
        });
    }

    /**
     * Forward WebRTC signal (SDP Offer, SDP Answer, or ICE candidate) to specific target peer.
     */
    public function forwardSignal(string $roomName, string $fromPeerId, ?string $toPeerId, array $signalData): array
    {
        return $this->withLockedRoom($roomName, function (Room $room) use ($fromPeerId, $toPeerId, $signalData) {
            $fromPeer = $room->getPeer($fromPeerId);
            if ($fromPeer === null) {
                return ['success' => false, 'error' => 'Sender peer not in room'];
            }

            $fromPeer->lastSeen = time();

            $room->addEvent('webrtc_signal', [
                'from_peer_id' => $fromPeerId,
                'from_nickname' => $fromPeer->nickname,
                'to_peer_id' => $toPeerId, // Null means broadcast to all
                'signal' => $signalData,
            ]);

            return [
                'success' => true,
                'seq' => $room->seqCounter,
            ];
        });
    }
}
