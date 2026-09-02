<?php

declare(strict_types=1);

namespace Salvon\Enum;

enum Locale: string
{
    final public const FULL_NAMES = [
        self::PL->value => 'Polski 🇵🇱',
        self::EN->value => 'English 🇬🇧/🇺🇸',
        self::DE->value => 'Deutsch 🇩🇪',
        self::FR->value => 'Français 🇫🇷',
        self::ES->value => 'Español 🇪🇸',
        self::IT->value => 'Italiano 🇮🇹',
        self::NL->value => 'Nederlands 🇳🇱',
        self::PT->value => 'Português 🇵🇹',
        self::SE->value => 'Svenska 🇸🇪',
        self::CZ->value => 'Český 🇨🇿',
        self::SK->value => 'Slovenský 🇸🇰',
    ];

    case PL = 'pl';
    case EN = 'en';
    case DE = 'de';
    case FR = 'fr';
    case ES = 'es';
    case IT = 'it';
    case NL = 'nl';
    case PT = 'pt';
    case SE = 'sv';
    case CZ = 'cz';
    case SK = 'sk';
}
