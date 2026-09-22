<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Co robi suma na końcu oferty (Z-Ż-06).
 *
 * `COMPONENTS` jest domyślne i jest sednem tego zgłoszenia: lista już
 * wie, czy jest składnikiem, czy alternatywą, więc suma sama pomija
 * warianty. Ręczne wyłączanie działa tylko wtedy, gdy ktoś pamięta —
 * rola listy pamięta zawsze.
 *
 * Pozostałe dwa są dla wyjątków: `ALL` sumuje wszystko, co na ofercie
 * (sensowne, gdy „warianty" to w istocie etapy), `NONE` chowa sumę
 * całkiem.
 */
enum OfferSumMode: string
{
    case COMPONENTS = 'components';
    case ALL = 'all';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::COMPONENTS => 'Sumuj składniki, pomiń warianty',
            self::ALL => 'Sumuj wszystko',
            self::NONE => 'Bez sumy',
        };
    }
}
