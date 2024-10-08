<?php

namespace App\Model\DataTypes;

final readonly class ReceivedInfo
{
    public function __construct(
        public string $gatewayId,
        public string $gatewayName,
        public int $rssi,
        public int $snr,
        public string $crcStatus,
        public Location $location,
    ) {
    }
}
