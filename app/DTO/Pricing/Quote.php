<?php

declare(strict_types=1);

namespace App\DTO\Pricing;

/**
 * Wynik wyceny wraz z pełną ścieżką wyliczenia.
 *
 * @property-read list<QuoteStep> $steps
 */
final readonly class Quote
{
    /** @param list<QuoteStep> $steps */
    public function __construct(
        public string $net,
        public array $steps,
        public float $billableSquareMeters,
        public float $runningMeters,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'net' => $this->net,
            'billable_m2' => $this->billableSquareMeters,
            'mb' => $this->runningMeters,
            'steps' => array_map(static fn(QuoteStep $step): array => $step->toArray(), $this->steps),
        ];
    }
}
