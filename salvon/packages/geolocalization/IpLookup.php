<?php

declare(strict_types=1);

namespace Salvon\Geolocalization;

class IpLookup
{
    public static function search(string $ip): LookupResult
    {
        $result = guzzle()->get(sprintf('https://web-api.nordvpn.com/v1/ips/lookup/%s', $ip));

        $data = json_decode($result->getBody()->getContents(), true);

        return new LookupResult(
            isp: self::filterValue($data, 'isp'),
            countryCode: self::filterValue($data, 'country_code'),
            country: self::filterValue($data, 'country'),
            region: self::filterValue($data, 'region'),
            city: self::filterValue($data, 'city'),
            postCode: self::filterValue($data, 'zip_code'),
            longitude: floatval($data['longitude']),
            latitude: floatval($data['latitude']),
        );
    }

    protected static function filterValue(array $data, string $key): ?string
    {
        $val = $data[$key] ?? null;
        return $val !== 'Unknown' ? $val : null;
    }
}
