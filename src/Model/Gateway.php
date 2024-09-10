<?php

namespace App\Model;

use App\Model\DataTypes\Location;
use App\Model\DataTypes\Status;

final readonly class Gateway
{
    public function __construct(
        public int $id,
        public string $gatewayId,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public \DateTimeImmutable $lastSeenAt,
        public string $name,
        public ?string $description,
        public Location $location,
        public Status $status,
    ) {
    }
}
