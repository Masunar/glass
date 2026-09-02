<?php

declare(strict_types=1);

namespace Salvon\Stooq;

enum StooqSymbol: string
{
    case GOLD_PLN = 'XAUPLN';
    case SILVER_PLN = 'XAGPLN';
    case SILVER_EUR = 'XAGEUR';
    case SILVER_USD = 'XAGUSD';
    case PLATINUM_PLN = 'XPTPLN';
    case PLATINUM_USD = 'XPTUSD';
    case PLATINUM_EUR = 'XPTEUR';
    case PALLADIUM_EUR = 'XPDEUR';
    case PALLADIUM_PLN = 'XPDPLN';
    case PALLADIUM_USD = 'XPDUSD';
    case EUR_PLN = 'EURPLN';
    case USD_PLN = 'USDPLN';
    case USD_EUR = 'USDEUR';
    case GOLD_USD = 'XAUUSD';
    case GOLD_EUR = 'XAUEUR';
    case PLN_EUR = 'PLNEUR';
    case PLN_USD = 'PLNUSD';
    case EUR_USD = 'EURUSD';

    public function isOzt(): bool
    {
        return in_array(substr($this->value, 0, 3), ['XAU', 'XAG', 'XPT', 'XPD'], true);
    }
}
