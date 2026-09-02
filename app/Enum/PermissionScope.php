<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Zakres danych widocznych dla roli.
 *
 * LOCATION realizuje rozdzielenie punktów (Stobno / Chopina) bez
 * izolowania danych w schemacie — lokalizacja jest atrybutem, a nie
 * granicą bazy.
 */
enum PermissionScope: string
{
    case ALL = 'all';
    case LOCATION = 'location';
    case OWN = 'own';
}
