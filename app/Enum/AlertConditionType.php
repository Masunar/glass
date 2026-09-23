<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Zamknięty katalog warunków, jakie potrafi policzyć silnik alertów.
 *
 * Reguła jest daną, ale **nie dowolną**: administrator wybiera typ
 * z tej listy i ustawia jego parametry. Alternatywą był warunek jako
 * swobodny JSON (pole, operator, wartość, składanie AND/OR) — czyli
 * własny język zapytań do napisania i utrzymania, w którym błąd wychodzi
 * dopiero na produkcji, bo nie ma czego sprawdzić przy zapisie.
 *
 * Tutaj odwrotnie: **nie da się zapisać reguły, której nikt nie umie
 * policzyć**. Nowy typ warunku to jedna klasa i wdrożenie — i to jest
 * świadomy koszt.
 */
enum AlertConditionType: string
{
    case ORDER_OVERDUE = 'order_overdue';
    case ORDER_CONTACT_OVERDUE = 'order_contact_overdue';
    case ORDER_MISSING_DRAWINGS = 'order_missing_drawings';
    case ORDER_NO_PAYMENT = 'order_no_payment';
    case ORDER_ON_HOLD = 'order_on_hold';
    case ORDER_OPEN_CLAIM = 'order_open_claim';
}
