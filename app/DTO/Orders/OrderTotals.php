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
 *
 * **VAT nie jest jedną liczbą.** Zlecenie może mieć kilka stawek naraz
 * (montaż 8 % obok szkła 23 %) i może mieć kwotę, której stawki nie
 * znamy. To trzy różne stany, nie jeden:
 *
 * | Stan | `vatRate` | `vat` / `gross` | `unknownNet` |
 * |---|---|---|---|
 * | jedna stawka | liczba | kwota | `0.00` |
 * | kilka stawek | `null` | kwota | `0.00` |
 * | stawka nieznana | `null` | `null` | kwota |
 *
 * Stąd `vatRate === null` nie wystarczy do niczego poza etykietą —
 * kto liczy brutto, bierze `gross`, a nie przelicza netto przez stawkę.
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
     * @param list<array{rate: int, net: string, vat: string, gross: string}> $vatLines
     */
    public function __construct(
        /** Suma pozycji przed rabatem, z list wliczonych do zlecenia. */
        public string $base,
        /** Rabat łącznie — kwotowo, nie procentowo: procenty per sekcja się nie sumują. */
        public string $discount,
        public string $net,
        /** Wartość list wyłączonych — odrzucone warianty, poza sumą. */
        public string $excludedNet,
        /** Jedyna stawka zlecenia. `null`, gdy jest ich kilka albo gdy którejś nie znamy. */
        public ?int $vatRate,
        public ?string $vat,
        public ?string $gross,
        /** Netto bez znanej stawki — powód, dla którego brutto jest `null`. */
        public string $unknownNet,
        /** Co dokładnie jest nieznane; `null`, gdy wszystko się liczy. */
        public ?string $unknownReason,
        public bool $mixedVat,
        public array $vatLines,
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
            'unknown_net' => $this->unknownNet,
            'unknown_reason' => $this->unknownReason,
            'mixed_vat' => $this->mixedVat,
            'vat_lines' => $this->vatLines,
            'sections' => $this->sections,
        ];
    }
}
