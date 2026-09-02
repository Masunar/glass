<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

use Salvon\UPS\Config;
use Salvon\UPS\Enum\UPSPackagingType;

final readonly class PackageData
{
    public function __construct(
        public float            $weight,
        public ?float           $length = null,
        public ?float           $width = null,
        public ?float           $height = null,
        public UPSPackagingType $packagingType = UPSPackagingType::CustomerSuppliedPackage,
        public ?string          $description = null,
        public ?float           $declaredValue = null,
        public ?string          $declaredValueCurrency = null,
        public ?InsureShieldData $insureShield = null,
        public ?string          $weightUnit = null,
        public ?string          $dimensionUnit = null,
    ) {}

    public function toUPSPayload(): array
    {
        $weightUnit = $this->weightUnit ?? Config::defaultWeightUnit();
        $dimensionUnit = $this->dimensionUnit ?? Config::defaultDimensionUnit();

        $payload = [
            'Packaging' => ['Code' => $this->packagingType->value],
            'PackageWeight' => [
                'UnitOfMeasurement' => ['Code' => $weightUnit],
                'Weight' => (string) $this->weight,
            ],
        ];

        if ($this->description !== null) {
            $payload['Description'] = $this->description;
        }

        if ($this->length !== null && $this->width !== null && $this->height !== null) {
            $payload['Dimensions'] = [
                'UnitOfMeasurement' => ['Code' => $dimensionUnit],
                'Length' => (string) $this->length,
                'Width' => (string) $this->width,
                'Height' => (string) $this->height,
            ];
        }

        $packageServiceOptions = [];

        if ($this->declaredValue !== null) {
            $packageServiceOptions['DeclaredValue'] = [
                'CurrencyCode' => $this->declaredValueCurrency ?? Config::defaultCurrency(),
                'MonetaryValue' => number_format($this->declaredValue, 2, '.', ''),
            ];
        }

        if ($packageServiceOptions !== []) {
            $payload['PackageServiceOptions'] = $packageServiceOptions;
        }

        return $payload;
    }
}
