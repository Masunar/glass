<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Typ parametru globalnego.
 *
 * Stary system trzymał wszystko jako tekst, przez co „25x4” i
 * „2250x3210” były parami liczb do sparsowania ze stringa, a numer
 * rachunku bankowego fragmentem zdania w polu „Warunki płatności”.
 */
enum GlobalParameterType: string
{
    case NUMBER = 'number';
    case PERCENT = 'percent';
    case TEXT = 'text';
    case IBAN = 'iban';
    case TEMPLATE = 'template';
}
