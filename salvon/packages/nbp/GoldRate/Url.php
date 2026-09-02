<?php

declare(strict_types=1);

namespace Salvon\NBP\GoldRate;

use Carbon\Carbon;

final readonly class Url
{
    public static function production(): string
    {
        return 'https://api.nbp.pl/api/cenyzlota';
    }

    public static function gateway(): string
    {
        return Url::production();
    }

    public static function get(string $url): string
    {
        return join_url(self::gateway(), $url);
    }

    public static function rateByDate(Carbon $date): string
    {
        return self::get($date->format('Y-m-d'));
    }

    public static function last(int $count): string
    {
        return self::get(join_url('last', (string) $count), );
    }

    public static function scope(Carbon $from, Carbon $to): string
    {
        return self::get(join_url($from->format('Y-m-d'), $to->format('Y-m-d')));
    }
}
