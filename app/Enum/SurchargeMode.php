<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Jak łączą się dopłaty za kształt i za gabaryt.
 *
 * Dokumentacja stawia tu znak zapytania (S-03), a różnica sięga
 * kilkudziesięciu procent na pozycji: przy cenie bazowej 1300 zł
 * kumulacja daje 2193,75, a sama najwyższa dopłata 1755,00.
 *
 * Dlatego jest to parametr, a nie warunek w kodzie — odpowiedź
 * odtworzona z danych starego systemu będzie zmianą wiersza w bazie.
 */
enum SurchargeMode: string
{
    /** Dopłaty mnożą się przez siebie. */
    case CUMULATIVE = 'cumulative';

    /** Obowiązuje wyłącznie najwyższa z dopłat. */
    case HIGHEST_ONLY = 'highest_only';
}
