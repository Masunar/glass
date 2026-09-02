<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

final readonly class AddressData
{
    /**
     * @param array<int, string> $addressLines
     */
    public function __construct(
        public array  $addressLines,
        public string $city,
        public string $postalCode,
        public string $countryCode,
        public ?string $stateProvinceCode = null,
        public bool   $residential = false,
    ) {}

    public function toUPSPayload(): array
    {
        $payload = [
            'AddressLine' => $this->addressLines,
            'City' => $this->city,
            'PostalCode' => $this->postalCode,
            'CountryCode' => $this->countryCode,
        ];

        if ($this->stateProvinceCode !== null) {
            $payload['StateProvinceCode'] = $this->stateProvinceCode;
        }

        if ($this->residential) {
            $payload['ResidentialAddressIndicator'] = '';
        }

        return $payload;
    }
}
