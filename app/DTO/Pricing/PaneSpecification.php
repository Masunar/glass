<?php

declare(strict_types=1);

namespace App\DTO\Pricing;

/**
 * Formatka do wyceny — to, co użytkownik wprowadza w zleceniu.
 *
 * Wymiary w milimetrach, tak jak w całym systemie i na hali.
 */
final readonly class PaneSpecification
{
    public function __construct(
        public int $widthMm,
        public int $heightMm,
        public int $quantity = 1,
        public bool $isIrregularShape = false,
        public bool $isTempered = false,
    ) {}

    /** Powierzchnia pojedynczej formatki — podstawa sprawdzenia gabarytu. */
    public function paneSquareMeters(): float
    {
        return ($this->widthMm / 1000) * ($this->heightMm / 1000);
    }

    /** Powierzchnia całej pozycji. */
    public function squareMeters(): float
    {
        return $this->paneSquareMeters() * $this->quantity;
    }

    /** Obwód pozycji w metrach bieżących — podstawa wyceny obróbki krawędzi. */
    public function runningMeters(): float
    {
        return (2 * ($this->widthMm + $this->heightMm) / 1000) * $this->quantity;
    }
}
