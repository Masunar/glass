<?php

declare(strict_types=1);

namespace Salvon\NBP\ExchangeRate;

enum NBPCurrency: string
{
    case THB = 'THB'; // BAT - Thailand
    case USD = 'USD'; // US Dollar
    case AUD = 'AUD'; // Australian Dollar
    case HDK = 'HDK'; // Hongkong Dollar
    case CAD = 'CAD'; // Canadian Dollar
    case NZD = 'NZD'; // New Zealand Dollar
    case SGD = 'SGD'; // Singapore Dollar
    case EUR = 'EUR'; // Euro
    case HUF = 'HUF'; // Hungarian Forint
    case CHF = 'CHF'; // Swiss Franc
    case GBP = 'GBP'; // British Pound
    case UAH = 'UAH'; // Ukrainian Hryvnia
    case JPY = 'JPY'; // Japanese Yen
    case CZK = 'CZK'; // Czech Koruna
    case DKK = 'DKK'; // Danish Krone
    case ISK = 'ISK'; // Icelandic Krona
    case NOK = 'NOK'; // Norwegian Krone
    case SEK = 'SEK'; // Swedish Krona
    case RON = 'RON'; // Romanian Leu
    case BGN = 'BGN'; // Bulgarian Lev
    case TRY = 'TRY'; // Turkish Lira
    case ILS = 'ILS'; // Israeli New Shekel
    case CLP = 'CLP'; // Chilean Peso
    case PHP = 'PHP'; // Philippine Peso
    case MXN = 'MXN'; // Mexican Peso
    case ZAR = 'ZAR'; // South African Rand
    case BRL = 'BRL'; // Brazilian Real
    case MYR = 'MYR'; // Malaysian Ringgit
    case IDR = 'IDR'; // Indonesian Rupiah
    case INR = 'INR'; // Indian Rupee
    case KRW = 'KRW'; // South Korean Won
    case CNY = 'CNY'; // Chinese Yuan
    case XDR = 'XDR'; // IMF Special Drawing Rights (SDR)
}
