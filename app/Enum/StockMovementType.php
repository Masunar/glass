<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rodzaj ruchu magazynowego.
 *
 * Stan magazynowy jest **sumą udokumentowanych zdarzeń**, nie liczbą,
 * którą ktoś nadpisuje. W starym systemie stan był polem w słowniku
 * produktów (`80-slowniki.md` §3.2) i dlatego zlecenie 16492 mogło mieć
 * status „Gotowe" przy zerowym stanie wszystkich okuć — nie dało się
 * odtworzyć, czy towar wydano, czy zlecenie przeszło mimo braku
 * (`40-magazyn.md` §4).
 *
 * Dlatego nawet **inwentaryzacja jest ruchem** (`CORRECTION`), a nie
 * poprawką stanu. Kto, kiedy i o ile — zostaje.
 *
 * Rezerwacja i zwolnienie nie ruszają stanu fizycznego, tylko
 * zarezerwowanego. Bez tego rozróżnienia dwa zlecenia „widzą" te same
 * trzy sztuki.
 */
enum StockMovementType: string
{
    /** Przyjęcie zewnętrzne — dostawa od dostawcy. */
    case RECEIPT = 'receipt';

    /** Rozchód wewnętrzny — wydanie na zlecenie. */
    case ISSUE = 'issue';

    /** Rezerwacja pod zlecenie. Stan fizyczny bez zmian. */
    case RESERVATION = 'reservation';

    /** Zwolnienie rezerwacji — anulowane zlecenie albo zmiana pozycji. */
    case RELEASE = 'release';

    /** Korekta po inwentaryzacji. Jedyna droga do „stan jest inny". */
    case CORRECTION = 'correction';

    /** Czy ruch zmienia stan fizyczny, czy tylko zarezerwowany. */
    public function touchesPhysical(): bool
    {
        return match ($this) {
            self::RECEIPT, self::ISSUE, self::CORRECTION => true,
            self::RESERVATION, self::RELEASE => false,
        };
    }

    /**
     * Znak, z jakim ruch wchodzi do swojego licznika.
     *
     * Korekta jest jedynym ruchem o obu znakach — ilość niesie wtedy
     * różnicę, a nie wartość bezwzględną.
     */
    public function sign(): int
    {
        return match ($this) {
            self::RECEIPT, self::RESERVATION, self::CORRECTION => 1,
            self::ISSUE, self::RELEASE => -1,
        };
    }

    /** Skrót dokumentu, jakim ruch jest w papierach. */
    public function document(): ?string
    {
        return match ($this) {
            self::RECEIPT => 'PZ',
            self::ISSUE => 'RW',
            default => null,
        };
    }
}
