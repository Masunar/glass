<?php

declare(strict_types=1);

namespace Salvon\Stooq;

final readonly class Url
{
    public static function production(): string
    {
        return 'https://stooq.com/q/l';
    }

    public static function gateway(): string
    {
        return Url::production();
    }

    public static function get(string $url): string
    {
        return join_url(self::gateway(), $url);
    }

    public static function symbols(StooqSymbol|string ...$symbols): string
    {
        $symbols = map($symbols, static fn(StooqSymbol $symbol) => $symbol->value);
        $symbols = implode('+', $symbols);
        return build_url_with_query(self::gateway(), ['s' => $symbols, 'f' => 'snd1t1cm3', 'e' => 'json']);
    }
}
