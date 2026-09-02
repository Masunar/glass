<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

use Salvon\UPS\Enum\UPSServiceCode;

final readonly class RatingData
{
    /**
     * @param array<int, PackageData> $packages
     */
    public function __construct(
        public PartyData      $shipper,
        public PartyData      $shipTo,
        public ?PartyData     $shipFrom,
        public array          $packages,
        public UPSServiceCode $service = UPSServiceCode::Standard,
    ) {}
}
