<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kategorie danych wrażliwych sterujące widocznością pól, nie ekranów.
 *
 * Produkcja i montaż pracują na tych samych dokumentach co biuro, ale
 * z ukrytymi kolumnami cenowymi — stąd rozdzielenie cen sprzedaży od
 * kosztów i marży: handlowiec musi widzieć ceny, a czy widzi zarobek,
 * jest osobną decyzją.
 */
enum SensitiveData: string
{
    case SALES_PRICES = 'sales_prices';
    case COSTS_AND_MARGIN = 'costs_and_margin';
    case CONTACT_DATA = 'contact_data';
    case FINANCIAL_DATA = 'financial_data';
}
