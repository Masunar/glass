<?php

declare(strict_types=1);

namespace App\DTO\Orders;

/**
 * Wycena formatki w kontekście zlecenia.
 *
 * Kwota szkła i kwoty procesów są rozdzielone, bo na zleceniu proces
 * jest osobnym wierszem — hala rozlicza cięcie i szlif niezależnie od
 * materiału. Ścieżka wyliczenia obejmuje jedno i drugie, żeby dało się
 * odpowiedzieć na jedyne pytanie, jakie ktoś zadaje o cenę: skąd ona.
 */
final readonly class PanePrice
{
    /**
     * @param list<array{
     *     process_id: int,
     *     code: string,
     *     label: string,
     *     unit_net_price: string,
     *     amount: string,
     *     unavailable: string|null
     * }> $processes
     * @param list<array<string, string|null>> $steps
     */
    public function __construct(
        /** Kwota samego materiału — bez procesów. */
        public string $glassNet,
        /** Cena metra kwadratowego, z której wyszła kwota materiału. */
        public ?string $netPricePerSquareMeter,
        public array $processes,
        public array $steps,
        public float $billableSquareMeters,
        public float $runningMeters,
        public float $weightKg,
        /**
         * Powód, dla którego ceny nie da się ustalić. Brak ceny jest
         * wynikiem z powodem, nie zerem — stary system wyceniał wtedy
         * pozycję na zero i wypuszczał ofertę z darmowym szkłem.
         */
        public ?string $unavailableReason = null,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason === null;
    }

    /** Kwota pozycji razem z procesami — to, co widzi klient. */
    public function total(): string
    {
        $total = (float) $this->glassNet;

        foreach ($this->processes as $process) {
            $total += (float) $process['amount'];
        }

        return number_format($total, 2, '.', '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'glass_net' => $this->glassNet,
            'net_price_per_m2' => $this->netPricePerSquareMeter,
            'total' => $this->total(),
            'processes' => $this->processes,
            'steps' => $this->steps,
            'billable_m2' => $this->billableSquareMeters,
            'mb' => $this->runningMeters,
            'weight_kg' => $this->weightKg,
            'unavailable_reason' => $this->unavailableReason,
        ];
    }
}
