<?php

declare(strict_types=1);

namespace Salvon\Stooq;

use Carbon\Carbon;
use Salvon\Service\Encoder;
use Psr\Http\Message\ResponseInterface;

class ResponseParser
{
    public static function single(ResponseInterface $response): ?StooqSymbolData
    {
        $content = $response->getBody()->getContents();
        $contentArray = Encoder::arrayFromJson($content);
        $symbols = $contentArray['symbols'] ?? [];
        $symbol = $symbols[0] ?? [];

        return self::parse($symbol);
    }

    public static function many(ResponseInterface $response): ?array
    {
        $content = $response->getBody()->getContents();
        $contentArray = Encoder::arrayFromJson($content);
        $symbols = $contentArray['symbols'] ?? [];

        $current = [];
        foreach ($symbols as $symbol) {
            $current[$symbol['symbol']] = self::parse($symbol);
        }

        return $current;
    }

    private static function parse(array $symbol): StooqSymbolData
    {
        $timestamp = sprintf('%s %s', $symbol['date'], $symbol['time']);
        $datetime = Carbon::createFromFormat('Ymd His', $timestamp);

        return new StooqSymbolData(
            name: $symbol['name'],
            symbol: $symbol['symbol'],
            value: $symbol['close'],
            change: $symbol['change'],
            changePercent: $symbol['change_pc'],
            dateTime: $datetime,
        );
    }
}
