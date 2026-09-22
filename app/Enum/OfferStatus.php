<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Stan oferty — własny, niezależny od statusu zlecenia.
 *
 * To była świadoma decyzja: oferta może być odrzucona, a zlecenie dalej
 * żyć, bo klient poprosił o drugi wariant. Sprzęgnięcie obu znaczyłoby,
 * że każde odrzucenie zamyka zlecenie.
 *
 * Nie ma stanu „szkic". Oferta powstaje w chwili wystawienia i od razu
 * jest migawką; dokument, który da się jeszcze zmieniać, nie jest
 * dowodem na to, co dostał klient.
 */
enum OfferStatus: string
{
    case ISSUED = 'issued';
    case SENT = 'sent';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::ISSUED => 'Wystawiona',
            self::SENT => 'Wysłana',
            self::ACCEPTED => 'Przyjęta',
            self::REJECTED => 'Odrzucona',
        };
    }

    /** Czy klient jeszcze nie odpowiedział. */
    public function isOpen(): bool
    {
        return match ($this) {
            self::ISSUED, self::SENT => true,
            self::ACCEPTED, self::REJECTED => false,
        };
    }
}
