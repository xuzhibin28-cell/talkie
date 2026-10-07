<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Represents a virtual communication channel room state.
 */
class Room
{
    /** @var array<string, Peer> */
    public array $peers = [];
    public ?string $speakerId = null;
    public ?string $speakerNickname = null;
    public ?int $speakerStartedAt = null; // Milliseconds timestamp
    public int $seqCounter = 0;
    public array $events = [];

    public function __construct(public string $name) {}

    public function addPeer(Peer $peer): void
    {
        $this->peers[$peer->id] = $peer;
    }

    public function removePeer(string $peerId): ?Peer
    {
        if (isset($this->peers[$peerId])) {
            $peer = $this->peers[$peerId];
            unset($this->peers[$peerId]);

            // Release floor if departing peer was speaking
            if ($this->speakerId === $peerId) {
                $this->clearSpeaker();
            }

            return $peer;
        }
        return null;
    }

    public function getPeer(string $peerId): ?Peer
    {
        return $this->peers[$peerId] ?? null;
    }

    public function setSpeaker(string $peerId, string $nickname): void
    {
        $this->speakerId = $peerId;
        $this->speakerNickname = $nickname;
        $this->speakerStartedAt = (int)round(microtime(true) * 1000);

        foreach ($this->peers as $p) {
            $p->isSpeaking = ($p->id === $peerId);
        }
    }

    public function clearSpeaker(): void
    {
        $this->speakerId = null;
        $this->speakerNickname = null;
        $this->speakerStartedAt = null;

        foreach ($this->peers as $p) {
            $p->isSpeaking = false;
        }
    }

    public function addEvent(string $type, array $data): array
    {
        $this->seqCounter++;
        $event = [
            'seq' => $this->seqCounter,
            'type' => $type,
            'data' => $data,
            'timestamp' => (int)round(microtime(true) * 1000),
        ];

        $this->events[] = $event;

        // Keep last 100 events in memory/disk to prevent unbounded file growth
        if (count($this->events) > 100) {
            $this->events = array_slice($this->events, -100);
        }

        return $event;
    }

    public function getEventsAfter(int $lastSeq): array
    {
        if ($lastSeq <= 0) {
            // Return latest 25 events if client is fresh
            return array_slice($this->events, -25);
        }

        $result = [];
        foreach ($this->events as $event) {
            if ($event['seq'] > $lastSeq) {
                $result[] = $event;
            }
        }
        return $result;
    }

    public function toArray(): array
    {
        $peersArr = [];
        foreach ($this->peers as $id => $peer) {
            $peersArr[$id] = $peer->toArray();
        }

        return [
            'name' => $this->name,
            'peers' => $peersArr,
            'speaker_id' => $this->speakerId,
            'speaker_nickname' => $this->speakerNickname,
            'speaker_started_at' => $this->speakerStartedAt,
            'seq_counter' => $this->seqCounter,
            'events' => $this->events,
        ];
    }

    public static function fromArray(string $name, array $data): self
    {
        $room = new self($name);
        $room->speakerId = $data['speaker_id'] ?? null;
        $room->speakerNickname = $data['speaker_nickname'] ?? null;
        $room->speakerStartedAt = isset($data['speaker_started_at']) ? (int)$data['speaker_started_at'] : null;
        $room->seqCounter = (int)($data['seq_counter'] ?? 0);
        $room->events = (array)($data['events'] ?? []);

        if (isset($data['peers']) && is_array($data['peers'])) {
            foreach ($data['peers'] as $peerData) {
                if (is_array($peerData)) {
                    $room->addPeer(Peer::fromArray($peerData));
                }
            }
        }

        return $room;
    }
}
