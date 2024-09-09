<?php

namespace App\Model;

use App\Model\DataTypes\Location;

final readonly class ReceivedInfo
{
    public function __construct(
        public int $rssi,
        public int $snr,
        public string $crcStatus,
        public Location $location,
    ) {
    }
}
