<?php

declare(strict_types=1);

namespace App\DTO\Orders;

/**
 * Wartość zlecenia — jedna odpowiedź dla wszystkich ekranów.
 *
 * Lista zleceń, karta i formatki pokazują tę samą kwotę. Dopóki każdy
 * z nich liczył ją u siebie, wystarczyła jedna poprawka w jednym
 * miejscu, żeby zaczęły się różnić — a wtedy nikt nie wie, która jest
 * prawdziwa.
 */
final readonly class OrderTotals
{
    /**
     * @param list<array{
     *     section: string,
     *     base: string,
     *     percent: string,
     *     discount: string,
     *     net: string
     * }> $sections
     */
    public function __construct(
        /** Suma pozycji przed rabatem, z list wliczonych do zlecenia. */
        public string $base,
        /** Rabat łącznie — kwotowo, nie procentowo: procenty per sekcja się nie sumują. */
        public string $discount,
        public string $net,
        /** Wartość list wyłączonych — odrzucone warianty, poza sumą. */
        public string $excludedNet,
        public ?int $vatRate,
        public ?string $vat,
        public ?string $gross,
        public array $sections,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'base' => $this->base,
            'discount' => $this->discount,
            'net' => $this->net,
            'excluded_net' => $this->excludedNet,
            'vat_rate' => $this->vatRate,
            'vat' => $this->vat,
            'gross' => $this->gross,
            'sections' => $this->sections,
        ];
    }
}
