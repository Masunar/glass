<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Jednostka rozliczeniowa pozycji.
 *
 * Szkło liczy się w metrach kwadratowych, obróbka krawędzi w metrach
 * bieżących, okucia i wiercenie w sztukach. W starym systemie ta
 * informacja siedziała w kolumnie `amount_type` typu tinyint
 * z komentarzem „1 - m2; 2 - mb; 3 - sztuki”.
 */
enum Unit: string
{
    case SQUARE_METER = 'm2';
    case RUNNING_METER = 'mb';
    case PIECE = 'pcs';
}
