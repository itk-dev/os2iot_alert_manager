<?php

namespace App\Model;

use App\Model\DataTypes\Status;

final readonly class Application
{
    public function __construct(
        public int $id,
        public \DateTime $createdAt,
        public \DateTime $updatedAt,
        public \DateTime $startDate,
        public \DateTime $endDate,
        public string $name,
        public Status $status,
        public string $contactPerson,
        public string $contactEmail,
        public string $contactPhone,
        /** @var array<string> */
        public array $devices,
    ) {
    }
}
