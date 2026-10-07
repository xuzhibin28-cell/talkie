<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Represents a connected channel participant.
 */
class Peer
{
    public function __construct(
        public string $id,
        public string $nickname,
        public int $joinedAt,
        public int $lastSeen,
        public bool $isSpeaking = false
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nickname' => $this->nickname,
            'joined_at' => $this->joinedAt,
            'last_seen' => $this->lastSeen,
            'is_speaking' => $this->isSpeaking,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            nickname: (string)($data['nickname'] ?? 'Guest'),
            joinedAt: (int)($data['joined_at'] ?? time()),
            lastSeen: (int)($data['last_seen'] ?? time()),
            isSpeaking: (bool)($data['is_speaking'] ?? false)
        );
    }
}
