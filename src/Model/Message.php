<?php

namespace App\Model;

final readonly class Message
{
    public function __construct(
        public int $id,
        public \DateTime $createdAt,
        public \DateTime $sentTime,
        public int $rssi,
        public int $snr,
    ) {
    }
}
