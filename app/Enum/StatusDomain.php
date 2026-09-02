<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Dziedzina słownika statusów.
 *
 * Każdy byt o własnym cyklu życia ma osobną dziedzinę w jednej tabeli
 * `statuses`. W starym systemie statusy zlecenia były zaszyte w nazwach
 * widoków SQL (v_valuations_1, _3, _5…), przez co dodanie statusu
 * wymagało napisania nowego widoku, a katalog rozjeżdżał się między
 * modułami. Tutaj status jest wierszem, a nie kodem.
 */
enum StatusDomain: string
{
    case ORDER = 'order';
    case MEASUREMENT = 'measurement';
    case COMPLAINT = 'complaint';
    case TEMPERING = 'tempering';
    case COMMISSION = 'commission';
}
