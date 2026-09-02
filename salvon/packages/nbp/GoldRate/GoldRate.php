<?php

declare(strict_types=1);

namespace Salvon\NBP\GoldRate;

use Carbon\Carbon;
use InvalidArgumentException;
use Salvon\Api\HasGuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

final class GoldRate
{
    use HasGuzzleClient;

    public static function get(Carbon $date = null): ?GoldRateData
    {
        if (!$date instanceof Carbon || $date->isFuture()) {
            $date = Carbon::now();
        }

        $client = self::guzzleClient();

        try {
            $response = $client->get(Url::rateByDate($date));
            return ResponseParser::daily($response);
        } catch (GuzzleException) {
            return null;
        }
    }

    /**
     * @return array<GoldRateData>
     *
     * @throws GuzzleException
     */
    public static function last(int $count = 10): array
    {
        if ($count > 255) {
            $count = 255;
        }

        $client = self::guzzleClient();

        $response = $client->get(Url::last($count));
        return ResponseParser::scoped($response);
    }

    /**
     * @return array<GoldRateData>
     *
     * @throws GuzzleException
     */
    public static function scope(Carbon $from, Carbon $to): array
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

        $response = $client->get(Url::scope($from, $to));
        return ResponseParser::scoped($response);
    }
}
