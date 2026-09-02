<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO;

final readonly class SearchResult
{
    public function __construct(
        public ?string $regon = null,
        public ?string $nip = null,
        public ?string $nipStatus = null,
        public ?string $name = null,
        public ?string $province = null,
        public ?string $district = null,
        public ?string $commune = null,
        public ?string $city = null,
        public ?string $postcode = null,
        public ?string $postcodeCity = null,
        public ?string $street = null,
        public ?string $propertyNumber = null,
        public ?string $apartmentNumber = null,
        public ?string $type = null,
        public ?string $silosId = null,
        public ?string $activityEndedAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'regon' => $this->regon,
            'nip' => $this->nip,
            'nip_status' => $this->nipStatus,
            'name' => $this->name,
            'province' => $this->province,
            'district' => $this->district,
            'commune' => $this->commune,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'postcode_city' => $this->postcodeCity,
            'street' => $this->street,
            'property_number' => $this->propertyNumber,
            'apartment_number' => $this->apartmentNumber,
            'type' => $this->type,
            'silos_id' => $this->silosId,
            'activity_ended_at' => $this->activityEndedAt,
        ];
    }
}
