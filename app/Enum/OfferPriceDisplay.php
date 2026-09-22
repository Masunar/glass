<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Czy oferta mówi do klienta kwotami netto, czy brutto (Z-Ż-03).
 *
 * To nie jest kosmetyka wydruku: firma liczy netto, bo VAT odlicza,
 * a klient detaliczny płaci brutto i netto go myli. Zapisane przy
 * ofercie, bo historia ma odpowiedzieć na pytanie „czy ostatnia była
 * netto czy brutto".
 *
 * ⚠️ Brutto wymaga znanej stawki. Zlecenie z kwotą bez stawki
 * (`OrderTotals::unknownNet`) nie da się pokazać brutto i wystawienie
 * takiej oferty jest odrzucane — lepiej zatrzymać się tutaj niż wysłać
 * klientowi kwotę, której nikt nie policzył.
 */
enum OfferPriceDisplay: string
{
    case NET = 'net';
    case GROSS = 'gross';

    public function label(): string
    {
        return match ($this) {
            self::NET => 'Netto',
            self::GROSS => 'Brutto',
        };
    }
}
