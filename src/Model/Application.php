<?php

namespace App\Model;

use App\Model\DataTypes\Status;

final readonly class Application
{
    public function __construct(
        public int $id,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $startDate,
        public ?\DateTimeImmutable $endDate,
        public string $name,
        public Status $status,
        public ?string $contactPerson,
        public ?string $contactEmail,
        public ?string $contactPhone,
        /** @var array<int> */
        public array $devices,
    ) {
    }
}
