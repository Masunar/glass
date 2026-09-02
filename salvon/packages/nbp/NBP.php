<?php

declare(strict_types=1);

namespace Salvon\NBP;

use Carbon\Carbon;
use Salvon\NBP\GoldRate\GoldRate;
use Salvon\NBP\GoldRate\GoldRateData;
use Salvon\NBP\ExchangeRate\NBPCurrency;
use Salvon\NBP\ExchangeRate\ExchangeRate;
use Salvon\NBP\ExchangeRate\ExchangeRateData;

final class NBP
{
    public static function exchangeRate(): ExchangeRate
    {
        return new ExchangeRate();
    }

    public static function goldRate(): GoldRate
    {
        return new GoldRate();
    }

    public static function currency(NBPCurrency $currency = NBPCurrency::EUR, Carbon $date = null): ?ExchangeRateData
    {
        return ExchangeRate::get($currency, $date);
    }

    public static function gold(Carbon $date = null): GoldRateData
    {
        return GoldRate::get($date);
    }
}
