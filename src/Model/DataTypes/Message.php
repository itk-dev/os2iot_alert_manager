<?php

namespace App\Model\DataTypes;

final readonly class Message
{
    public function __construct(
        public int $id,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $sentTime,
        public int $rssi,
        public int $snr,
        /** @var array<ReceivedInfo> $rxInfo */
        public array $rxInfo,
    ) {
    }
}
