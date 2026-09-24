<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kształt formatki.
 *
 * Był tu przełącznik tak/nie („kształt nieregularny"), a owalu nie dało
 * się zapisać inaczej niż jako kształt — bez śladu, że to owal. Rodzaj
 * zamiast flagi, bo odpowiada na dwa pytania naraz: czy wchodzi dopłata
 * za kształt i czy formatka potrzebuje rysunku.
 */
enum PaneShape: string
{
    case RECTANGLE = 'rectangle';
    case IRREGULAR = 'irregular';
    case OVAL = 'oval';

    public function label(): string
    {
        return match ($this) {
            self::RECTANGLE => 'Prostokąt',
            self::IRREGULAR => 'Kształt',
            self::OVAL => 'Owal',
        };
    }

    /**
     * Czy wchodzi dopłata za kształt.
     *
     * **Owal płaci na razie tak samo jak kształt** — osobnej stawki nikt
     * jeszcze nie ustalił, a owal bez dopłaty byłby tańszy od prostokąta
     * z wycięciem, co na pewno nie jest zamiarem.
     */
    public function hasShapeSurcharge(): bool
    {
        return $this !== self::RECTANGLE;
    }

    /** Kształt i owal wytnie się tylko z rysunku (decyzja Marcina). */
    public function needsDrawing(): bool
    {
        return $this !== self::RECTANGLE;
    }
}
