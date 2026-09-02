<?php

declare(strict_types=1);

namespace Salvon\NBP\ExchangeRate;

use Carbon\Carbon;
use Salvon\Service\Encoder;
use Psr\Http\Message\ResponseInterface;

class ResponseRatesExtractor
{
    public static function daily(ResponseInterface $response, NBPCurrency $currency): ?ExchangeRateData
    {
        $content = $response->getBody()->getContents();

        $contentArray = Encoder::arrayFromJson($content);
        $rates = $contentArray['rates'];
        $rate = $rates[0] ?? null;

        if (empty($rate['mid'] ?? null)) {
            return null;
        }

        return self::parse($rate['mid'], $currency->value, $rate['effectiveDate'], $rate['no']);
    }

    public static function currencyScoped(ResponseInterface $response, NBPCurrency $currency): array
    {
        $content = $response->getBody()->getContents();

        $contentArray = Encoder::arrayFromJson($content);
        $rates = $contentArray['rates'] ?? [];

        $preparedRates = [];
        foreach ($rates as $rate) {
            $parsedRate = self::parse($rate['mid'], $currency->value, $rate['effectiveDate'], $rate['no']);
            $preparedRates[$parsedRate->date->format('Y-m-d')] = $parsedRate;
        }

        return $preparedRates;
    }

    public static function dailyTable(ResponseInterface $response): ?array
    {
        $content = $response->getBody()->getContents();

        $contentArray = Encoder::arrayFromJson($content)[0] ?? [];
        $rates = $contentArray['rates'] ?? null;

        if (empty($rates)) {
            return null;
        }

        return map($rates, static function (array $rate): ExchangeRateData {
            return self::parse($rate['mid'], $rate['currency'], Carbon::now());
        });
    }

    private static function parse(float $value, string $currency, Carbon|string $date, ?string $number = null): ExchangeRateData
    {
        if (is_string($date)) {
            $date = Carbon::createFromFormat('Y-m-d', $date);
        }

        return new ExchangeRateData($currency, $value, $date, $number);
    }
}
