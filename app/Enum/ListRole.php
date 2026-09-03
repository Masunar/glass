<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rola listy w zleceniu.
 *
 * Lista obsługuje dwa różne przypadki naraz: kompozycję (kilka
 * pomieszczeń = kilka list, wszystkie wliczone) i wariantowanie oferty
 * (szkło 8 mm vs 6 mm, jedna wliczona, reszta zostaje w historii).
 *
 * Bez jawnego rozróżnienia klient dostaje sumę dwóch alternatyw.
 */
enum ListRole: string
{
    case COMPONENT = 'component';
    case ALTERNATIVE = 'alternative';
}
