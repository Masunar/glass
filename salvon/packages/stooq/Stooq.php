<?php

declare(strict_types=1);

namespace Salvon\Stooq;

use Salvon\Api\HasGuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

final class Stooq
{
    use HasGuzzleClient;

    /**
     * @throws GuzzleException
     */
    public static function symbol(StooqSymbol|string $symbol): StooqSymbolData
    {
        $client = self::guzzleClient();
        $response = $client->get(Url::symbols($symbol));

        return ResponseParser::single($response);
    }

    /**
     * @return array<StooqSymbolData>
     *@throws GuzzleException
     */
    public static function many(StooqSymbol|string ...$symbols): array
    {
        $client = self::guzzleClient();
        $response = $client->get(Url::symbols(...$symbols));

        return ResponseParser::many($response);
    }
}
