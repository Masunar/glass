<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Skąd wzięła się cena cennikowa produktu.
 *
 * Rozróżnienie ma znaczenie przy C-01: w starym systemie komórka
 * cennika trzymała dwie liczby udające cenę — ręcznie zaokrągloną
 * `Cena` (84) i wyliczoną `Cena N` (83,60). Do wyceny szła wyliczona,
 * więc ta pierwsza wprowadzała w błąd każdego, kto na nią patrzył.
 * Tutaj obowiązuje jedna cena, a to pole mówi, która to jest.
 */
enum PriceSource: string
{
    /** Cena wyliczona: cena zakupu × współczynnik. */
    case COMPUTED = 'computed';

    /** Cena wpisana ręcznie, nadpisująca wyliczenie. */
    case MANUAL = 'manual';

    /** Cena indywidualna kontrahenta — nadrzędna wobec cennika. */
    case INDIVIDUAL = 'individual';
}
