<?php

declare(strict_types=1);

namespace App\DTO\Pricing;

use App\Enum\PriceSource;
use App\Enum\PriceUnavailableReason;

/**
 * Cena jednostkowa produktu wraz z tym, skąd się wzięła.
 *
 * Zwracana zawsze — również wtedy, gdy ceny nie ma. Brak ceny jest
 * wynikiem z powodem, nie zerem: stary system wyceniał wtedy pozycję
 * na zero i wypuszczał ofertę z darmowym szkłem.
 */
final readonly class ResolvedPrice
{
    /** @param list<QuoteStep> $steps */
    private function __construct(
        public ?string $netPrice,
        public ?PriceSource $source,
        public ?PriceUnavailableReason $unavailableReason,
        public ?string $coefficient,
        public ?string $purchaseNetPrice,
        public ?string $recomputedNetPrice,
        public ?int $priceSectionId,
        public ?string $priceSectionName,
        public array $steps,
    ) {}

    /** @param list<QuoteStep> $steps */
    public static function of(
        string $netPrice,
        PriceSource $source,
        ?string $coefficient,
        ?string $purchaseNetPrice,
        ?string $recomputedNetPrice,
        ?int $priceSectionId,
        ?string $priceSectionName,
        array $steps = [],
    ): self {
        return new self(
            netPrice: $netPrice,
            source: $source,
            unavailableReason: null,
            coefficient: $coefficient,
            purchaseNetPrice: $purchaseNetPrice,
            recomputedNetPrice: $recomputedNetPrice,
            priceSectionId: $priceSectionId,
            priceSectionName: $priceSectionName,
            steps: $steps,
        );
    }

    public static function unavailable(
        PriceUnavailableReason $reason,
        ?int $priceSectionId = null,
        ?string $priceSectionName = null,
    ): self {
        return new self(
            netPrice: null,
            source: null,
            unavailableReason: $reason,
            coefficient: null,
            purchaseNetPrice: null,
            recomputedNetPrice: null,
            priceSectionId: $priceSectionId,
            priceSectionName: $priceSectionName,
            steps: [],
        );
    }

    public function isAvailable(): bool
    {
        return $this->netPrice !== null;
    }

    /**
     * Czy cennikowa cena rozjechała się z bieżącą ceną zakupu.
     *
     * Cena w cenniku jest zamrożona do momentu świadomego zapisu —
     * to odpowiedź na ryzyko z `50-cennik.md` §6, gdzie każda dostawa
     * po nowej cenie po cichu przeliczała cały cennik i oferta
     * wystawiona wczoraj przestawała być aktualna dzisiaj. Rozjazd nie
     * jest błędem, tylko informacją dla osoby prowadzącej cennik.
     */
    public function isStale(): bool
    {
        return $this->source === PriceSource::COMPUTED
            && $this->recomputedNetPrice !== null
            && $this->netPrice !== null
            && $this->recomputedNetPrice !== $this->netPrice;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'net_price' => $this->netPrice,
            'source' => $this->source?->value,
            'unavailable_reason' => $this->unavailableReason?->value,
            'coefficient' => $this->coefficient,
            'purchase_net_price' => $this->purchaseNetPrice,
            'recomputed_net_price' => $this->recomputedNetPrice,
            'is_stale' => $this->isStale(),
            'price_section_id' => $this->priceSectionId,
            'price_section_name' => $this->priceSectionName,
            'steps' => array_map(static fn(QuoteStep $step): array => $step->toArray(), $this->steps),
        ];
    }
}
