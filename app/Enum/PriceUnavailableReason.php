<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Dlaczego produkt nie ma ceny sprzedaży.
 *
 * Stary system w takiej sytuacji wyceniał pozycję na zero i szedł
 * dalej — oferta wychodziła do klienta z darmowym szkłem. Powód jest
 * zwracany zamiast kwoty, żeby dało się powiedzieć, czego brakuje
 * i gdzie to uzupełnić.
 */
enum PriceUnavailableReason: string
{
    /** Sekcja asortymentu nie ma sekcji cenowej oznaczonej jako domyślna. */
    case NO_PRICE_SECTION = 'no_price_section';

    /** Produkt nie ma pozycji w cenniku dla tej sekcji cenowej. */
    case NO_PRICE_LIST_ITEM = 'no_price_list_item';

    /** Pozycja cennika istnieje, ale nie ma z czego wyliczyć ceny. */
    case NO_PRICE = 'no_price';
}
