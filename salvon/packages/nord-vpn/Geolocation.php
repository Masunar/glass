<?php

declare(strict_types=1);

namespace Salvon\NordVPN;

class Geolocation
{
    public static function lookup(string $ip): array
    {
        $result = guzzle()->get(sprintf('https://web-api.nordvpn.com/v1/ips/lookup/%s', $ip));

        return json_decode($result->getBody()->getContents(), true);
    }
}
