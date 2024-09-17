<?php

namespace App\Model;

use App\Model\DataTypes\Location;
use App\Model\DataTypes\Message;

final readonly class Device
{
    public function __construct(
        public int $id,
        public int $applicationId,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public string $name,
        public Location $location,
        public ?Message $latestReceivedMessage,
        public float $statusBattery,
        public array $metadata,
    ) {
    }
}
