<?php

declare(strict_types=1);

namespace Salvon\Geolocalization;

final readonly class LookupResult
{
    public function __construct(
        public ?string $isp = null,
        public ?string $countryCode = null,
        public ?string $country = null,
        public ?string $region = null,
        public ?string $city = null,
        public ?string $postCode = null,
        public ?float  $longitude = null,
        public ?float  $latitude = null,
    ) {}
}
