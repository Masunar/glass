<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

final readonly class PartyData
{
    public function __construct(
        public string  $name,
        public AddressData $address,
        public ?string $attentionName = null,
        public ?string $companyDisplayableName = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $taxIdentificationNumber = null,
    ) {}

    public function toUPSPayload(bool $isShipper = false, ?string $shipperNumber = null): array
    {
        $payload = [
            'Name' => $this->name,
            'Address' => $this->address->toUPSPayload(),
        ];

        if ($this->attentionName !== null) {
            $payload['AttentionName'] = $this->attentionName;
        }

        if ($this->phone !== null) {
            $payload['Phone'] = ['Number' => $this->phone];
        }

        if ($this->email !== null) {
            $payload['EMailAddress'] = $this->email;
        }

        if ($this->taxIdentificationNumber !== null) {
            $payload['TaxIdentificationNumber'] = $this->taxIdentificationNumber;
        }

        if ($isShipper && $shipperNumber !== null) {
            $payload['ShipperNumber'] = $shipperNumber;
        }

        return $payload;
    }
}
