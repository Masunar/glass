<?php

declare(strict_types=1);

namespace Salvon\Facade;

use Rinvex\Country\CountryLoaderException;
use Rinvex\Country\Country as RinvexCountry;

final class Country
{
    public static function fromIso(string $isoCode): ?RinvexCountry
    {
        try {
            return country($isoCode);
        } catch (CountryLoaderException) {
            return null;
        }
    }

    public static function arrayFromIso(string $isoCode): ?array
    {
        $country = self::fromIso($isoCode);

        if (!$country instanceof RinvexCountry) {
            return null;
        }

        return self::asArray($country);
    }

    public static function asArray(RinvexCountry $country): array
    {
        return [
            'name' => $country->getName(),
            'official_name' => $country->getOfficialName(),
            'native_official_name' => $country->getNativeOfficialName(),
            'iso_3166_1_alpha2' => $country->getIsoAlpha2(),
            'iso_3166_1_alpha3' => $country->getIsoAlpha3(),
            'calling_code' => $country->getCallingCode(),
            'currency' => $country->getCurrency()['iso_4217_code'] ?? '',
            'emoji' => $country->getEmoji(),
            'display_name' => $country->getNativeName() . ' ' . $country->getEmoji(),
        ];
    }
}
