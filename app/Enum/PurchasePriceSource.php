<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Skąd wzięła się cena zakupu.
 *
 * W starym systemie każda dostawa po nowej cenie automatycznie
 * przeliczała cały cennik, więc oferta wystawiona wczoraj przestawała
 * być aktualna dzisiaj. Rozdzielenie kosztu ewidencyjnego od ceny
 * cennikowej zaczyna się od zapisania, skąd liczba pochodzi.
 */
enum PurchasePriceSource: string
{
    case DELIVERY = 'delivery';
    case MANUAL = 'manual';
    case WEIGHTED_AVERAGE = 'weighted_average';
}
