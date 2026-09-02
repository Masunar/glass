<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO\CompanyReport;

final readonly class Address
{
    public function __construct(
        public ?string $countrySymbol = null, //adSiedzKraj_Symbol
        public ?string $provinceSymbol = null, //adSiedzWojewodztwo_Symbol
        public ?string $districtSymbol = null, //adSiedzPowiat_Symbol
        public ?string $communeSymbol = null, //adSiedzGmina_Symbol
        public ?string $postcode = null, //adSiedzKodPocztowy
        public ?string $postcodeCitySymbol = null, //adSiedzMiejscowoscPoczty_Symbol
        public ?string $streetSymbol = null, //adSiedzUlica_Symbol
        public ?string $buildingNumber = null, //adSiedzNumerNieruchomosci
        public ?string $apartmentNumber = null, //adSiedzNumerLokalu
        public ?string $unusualPlaceLocation = null, //adSiedzNietypoweMiejsceLokalizacji
        public ?string $countryName = null, //adSiedzKraj_Nazwa
        public ?string $provinceName = null, //adSiedzWojewodztwo_Nazwa
        public ?string $districtName = null, //adSiedzPowiat_Nazwa
        public ?string $communeName = null, //adSiedzGmina_Nazwa
        public ?string $cityName = null, //adSiedzMiejscowosc_Nazwa
        public ?string $postcodeCityName = null, //adSiedzMiejscowoscPoczty_Nazwa
        public ?string $streetName = null, //adSiedzUlica_Nazwa
    ) {}
}
