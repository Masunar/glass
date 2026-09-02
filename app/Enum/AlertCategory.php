<?php

declare(strict_types=1);

namespace App\Enum;

/** Kategoria reguły alertu — patrz dokumentacja, przegląd §5.2. */
enum AlertCategory: string
{
    case DEADLINE = 'deadline';
    case PAYMENTS = 'payments';
    case MISSING_DATA = 'missing_data';
    case APPROVAL = 'approval';
    case QUALITY = 'quality';
}
