<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

use Salvon\UPS\Enum\UPSLabelFormat;
use Salvon\UPS\Enum\UPSServiceCode;

final readonly class ShipmentData
{
    /**
     * @param array<int, PackageData> $packages
     * @param array<int, string>      $description
     */
    public function __construct(
        public PartyData      $shipper,
        public PartyData      $shipTo,
        public ?PartyData     $shipFrom,
        public array          $packages,
        public UPSServiceCode $service = UPSServiceCode::Standard,
        public UPSLabelFormat $labelFormat = UPSLabelFormat::PDF,
        public ?string        $description = null,
        public ?string        $referenceNumber = null,
        public ?string        $paymentAccountNumber = null,
    ) {}
}
