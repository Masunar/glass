<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kanał wpłaty.
 *
 * Rozbicie jednowymiarowego słownika „Typy wpłat”, który mieszał kanał
 * (gotówka / przelew), kasę lub osobę (Chopina, Stobno, MarekB)
 * i walutę (PLN, EUR, USD) w jednej liście. Przez to nie dało się
 * zapytać ani o wszystkie wpłaty gotówkowe, ani o wszystko w euro.
 */
enum PaymentChannel: string
{
    case CASH = 'cash';
    case TRANSFER = 'transfer';
}
