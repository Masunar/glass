<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rodzaj problemu zgłoszonego ze stanowiska.
 *
 * Cztery rodzaje wprost z dokumentacji modułu (`70-produkcja.md` §11).
 * Bez kosztu i bez powiązania z reklamacją — dziś nie wiadomo, jak
 * stłuczka jest rozliczana (P-13), a zgadnięta kwota w księgach jest
 * gorsza niż jej brak.
 */
enum ProductionIssue: string
{
    case BREAKAGE = 'breakage';
    case MATERIAL = 'material';
    case DRAWING = 'drawing';
    case REWORK = 'rework';
}
