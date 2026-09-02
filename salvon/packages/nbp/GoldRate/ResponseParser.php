<?php

declare(strict_types=1);

namespace Salvon\NBP\GoldRate;

use Carbon\Carbon;
use Salvon\Service\Encoder;
use Psr\Http\Message\ResponseInterface;

class ResponseParser
{
    public static function daily(ResponseInterface $response): ?GoldRateData
    {
        $content = $response->getBody()->getContents();

        $contentArray = Encoder::arrayFromJson($content);
        $rate = $contentArray[0] ?? null;

        if (empty($rate['cena'] ?? null)) {
            return null;
        }

        return self::parse($rate);
    }

    /** @return array<GoldRateData> */
    public static function scoped(ResponseInterface $response): array
    {
        $content = $response->getBody()->getContents();

        $rates = Encoder::arrayFromJson($content);

        $preparedRates = [];
        foreach ($rates as $rate) {
            $parsedRate = self::parse($rate);
            $preparedRates[$parsedRate->date->format('Y-m-d')] = $parsedRate;
        }

        return $preparedRates;
    }

    private static function parse(array $rate): GoldRateData
    {
        $date = Carbon::createFromFormat('Y-m-d', $rate['data']);
        return new GoldRateData((float) $rate['cena'], $rate['data'] ?? '', $date);
    }
}
