<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Ile oferta pokazuje klientowi.
 *
 * `SUMMARY` jest domyślne (Z-Ż-05): jedna kwota na listę, czyli na
 * pomieszczenie albo wariant. `DETAILED` rozpisuje każdą formatkę
 * z wymiarami, procesami i ceną jednostkową.
 *
 * Domyślna jest ta krótsza, bo rozpiska pokazuje klientowi wszystko,
 * z czego składa się cena — łącznie z tym, na czym zarabiamy. Kto
 * chce ją pokazać, robi to świadomie.
 */
enum OfferDetailLevel: string
{
    case SUMMARY = 'summary';
    case DETAILED = 'detailed';

    public function label(): string
    {
        return match ($this) {
            self::SUMMARY => 'Nieszczegółowa — kwota na listę',
            self::DETAILED => 'Szczegółowa — pozycja po pozycji',
        };
    }
}
