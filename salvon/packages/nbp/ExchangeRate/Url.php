<?php

declare(strict_types=1);

namespace Salvon\NBP\ExchangeRate;

use Carbon\Carbon;

final readonly class Url
{
    public static function production(): string
    {
        return 'https://api.nbp.pl/api/exchangerates';
    }

    public static function gateway(): string
    {
        return Url::production();
    }

    public static function get(string $url): string
    {
        return join_url(self::gateway(), $url);
    }

    public static function rateByDate(NBPCurrency $currency, Carbon $date): string
    {
        return self::get(
            join_url('/rates/A/', $currency->value, $date->format('Y-m-d')),
        );
    }

    public static function currencyLast(NBPCurrency $currency, int $count): string
    {
        return self::get(
            join_url('/rates/A/', $currency->value, 'last', (string) $count),
        );
    }

    public static function currencyScope(NBPCurrency $currency, Carbon $from, Carbon $to): string
    {
        return self::get(
            join_url('/rates/A/', $currency->value, $from->format('Y-m-d'), $to->format('Y-m-d')),
        );
    }

    public static function dailyTable(): string
    {
        return self::get('/tables/A/today');
    }
}
