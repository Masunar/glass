<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Faza procesu zlecenia — grupa statusów na pasku listy.
 *
 * Dwanaście statusów w jednym rzędzie zakładek nie mieściło się na
 * żadnym ekranie: przewijany pasek ucinał „W toku" w połowie słowa,
 * a przełącznik „tylko moje" łamał się na dwie linie. Fazy czytają się
 * jak proces — wycena, realizacja, wydanie, rozliczenie — i zawsze
 * mieszczą w jednym rzędzie.
 *
 * **Przypisanie statusu do fazy jest jawne, po kodzie.** Wyprowadzanie
 * go z pozycji w słowniku (10, 20…) byłoby zgadywaniem znaczenia
 * z liczby. Status, którego tu nie ma — dodany później w słowniku —
 * trafia do fazy „Inne", a nie znika z paska.
 */
enum OrderPhase: string
{
    case QUOTE = 'quote';
    case PRODUCTION = 'production';
    case HANDOVER = 'handover';
    case SETTLEMENT = 'settlement';
    case OTHER = 'other';
    case CLOSED = 'closed';

    private const MAP = [
        'DO_WYCENY' => self::QUOTE,
        'ZLECENIE' => self::PRODUCTION,
        'PRODUKCJA' => self::PRODUCTION,
        'GOTOWE' => self::PRODUCTION,
        'DOSTAWA' => self::HANDOVER,
        'ODBIOR' => self::HANDOVER,
        'MONTAZ' => self::HANDOVER,
        'NIEROZLICZONE' => self::SETTLEMENT,
        'ROZLICZONE' => self::SETTLEMENT,
    ];

    /**
     * Status końcowy jest zamknięty niezależnie od kodu — o tym, czy
     * zlecenie się skończyło, decyduje słownik, nie ta lista.
     */
    public static function forStatus(string $code, bool $isFinal): self
    {
        if ($isFinal) {
            return self::CLOSED;
        }

        return self::MAP[$code] ?? self::OTHER;
    }

    public function label(): string
    {
        return match ($this) {
            self::QUOTE => 'Wycena',
            self::PRODUCTION => 'Realizacja',
            self::HANDOVER => 'Wydanie',
            self::SETTLEMENT => 'Rozliczenie',
            self::OTHER => 'Inne',
            self::CLOSED => 'Zamknięte',
        };
    }
}
