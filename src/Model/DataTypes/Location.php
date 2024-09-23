<?php

namespace App\Model\DataTypes;

final readonly class Location
{
    public function __construct(
        public string $latitude,
        public string $longitude,
    ) {
    }
}
