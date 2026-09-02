<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rola adresu kontrahenta.
 *
 * Stary system miał jeden adres, a firma z centralą w Warszawie odbiera
 * szkło w Szczecinie i fakturę dostaje pod trzecim adresem.
 */
enum AddressKind: string
{
    case REGISTERED = 'registered';
    case CORRESPONDENCE = 'correspondence';
    case DELIVERY = 'delivery';
}
