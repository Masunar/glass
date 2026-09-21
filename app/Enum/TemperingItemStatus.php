<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Los jednej pozycji w hartowni.
 *
 * `BROKEN` i `MISSING` to nie są stany końcowe pozycji, tylko powody,
 * dla których szkła nadal nie ma: obie zakładają pozycję zastępczą
 * w kolejce. W starym systemie stłuczki nie rejestrowano wcale —
 * formatka nie wracała, a zlecenie stało bez wyjaśnienia
 * (`20-hartownia.md` §4).
 */
enum TemperingItemStatus: string
{
    case QUEUED = 'queued';
    case SENT = 'sent';
    case RETURNED = 'returned';
    case BROKEN = 'broken';
    case MISSING = 'missing';
    case REWORK = 'rework';

    /**
     * Czy ta pozycja pokrywa zapotrzebowanie formatki.
     *
     * Stłuczka i brak nie pokrywają — szkła nie ma i trzeba je zrobić
     * od nowa. Poprawka pokrywa: szyba wróciła, tylko wymaga czegoś
     * jeszcze, i nie jedzie drugi raz do pieca jako nowa sztuka.
     */
    public function covers(): bool
    {
        return match ($this) {
            self::QUEUED, self::SENT, self::RETURNED, self::REWORK => true,
            self::BROKEN, self::MISSING => false,
        };
    }

    /** Czy pozycję można jeszcze dopisać do partii albo skasować. */
    public function isQueued(): bool
    {
        return $this === self::QUEUED;
    }

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'W kolejce',
            self::SENT => 'Wysłana',
            self::RETURNED => 'Wróciła',
            self::BROKEN => 'Stłuczka',
            self::MISSING => 'Brak',
            self::REWORK => 'Do poprawki',
        };
    }
}
