<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Sekcja asortymentu — podstawowy wymiar podziału w całym systemie.
 *
 * Po sekcjach przypisuje się cenniki kontrahentom, nadaje rabaty na
 * zleceniu i grupuje produkty w magazynie. Wartości odpowiadają
 * słownikowi domenowemu: Szkło / Okucia / Usługi / Inne.
 */
enum Section: string
{
    case GLASS = 'glass';
    case FITTINGS = 'fittings';
    case SERVICES = 'services';
    case OTHER = 'other';
}
