<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kiedy sprawdzany jest próg minimalnej ceny formatki.
 *
 * Pytanie S-04 z dokumentacji. Dla formatki 0,2 m² wycenionej na 52 zł
 * z nieregularnym kształtem: sprawdzenie przed dopłatami daje
 * 52 × 1,5 × 1,35 = 105,30, a po dopłatach 52 × 1,35 = 70,20, bo próg
 * 60 zł zostaje wtedy przekroczony i dopłata w ogóle nie wchodzi.
 */
enum MinPriceCheck: string
{
    case BEFORE_SURCHARGES = 'before_surcharges';
    case AFTER_SURCHARGES = 'after_surcharges';
}
