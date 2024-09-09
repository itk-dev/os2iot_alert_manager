<?php

namespace App\Model;

use App\Model\DataTypes\Location;

final readonly class Device
{
    public function __construct(
        public int $id,
        public \DateTime $createdAt,
        public \DateTime $updatedAt,
        public string $name,
        public Location $location,
        public Application $application,
        public Message $latestReceivedMessage,
        public float $statusBattery,
    ) {
    }
}
