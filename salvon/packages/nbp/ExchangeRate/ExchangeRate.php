<?php

declare(strict_types=1);

namespace Salvon\NBP\ExchangeRate;

use Carbon\Carbon;
use InvalidArgumentException;
use Salvon\Api\HasGuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

final class ExchangeRate
{
    use HasGuzzleClient;

    /**
     * @return array<ExchangeRateData>|null
     *
     * @throws GuzzleException
     */
    public static function all(): ?array
    {
        $client = self::guzzleClient();

        $response = $client->get(Url::dailyTable());
        return ResponseRatesExtractor::dailyTable($response);
    }

    public static function get(NBPCurrency $currency, Carbon $date = null): ?ExchangeRateData
    {
        if (!$date instanceof Carbon || $date->isFuture()) {
            $date = Carbon::now();
        }

        $client = self::guzzleClient();

        try {
            $response = $client->get(Url::rateByDate($currency, $date));
            return ResponseRatesExtractor::daily($response, $currency);
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * @return array<ExchangeRateData>
     *
     * @throws GuzzleException
     */
    public static function last(NBPCurrency $currency, int $count = 10): array
    {
        if ($count > 255) {
            $count = 255;
        }

        $client = self::guzzleClient();

        $response = $client->get(Url::currencyLast($currency, $count));
        return ResponseRatesExtractor::currencyScoped($response, $currency);
    }

    /**
     * @return array<ExchangeRateData>
     *
     * @throws GuzzleException
     */
    public static function scope(NBPCurrency $currency, Carbon $from, Carbon $to): array
    {
        if ($from->isFuture()) {
            $from = Carbon::now();
        }

        if ($to->isFuture()) {
            $to = Carbon::now();
        }

        if ((int) $from->diffInDays($to) > 367) {
            throw new InvalidArgumentException("Difference between dates can't exceed 367 days.");
        }

        $client = self::guzzleClient();

        $response = $client->get(Url::currencyScope($currency, $from, $to));
        return ResponseRatesExtractor::currencyScoped($response, $currency);
    }
}
