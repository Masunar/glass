<?php

declare(strict_types=1);

namespace App\DTO\Orders;

use App\Enum\InvestmentType;

/**
 * Podział stawki obniżonej na zleceniu z inwestycją mieszkaniową.
 *
 * Art. 41 ust. 12b ustawy o VAT wiąże stawkę obniżoną z limitem
 * powierzchni użytkowej, a ust. 12c każe przy przekroczeniu limitu
 * rozbić podstawę proporcjonalnie: część odpowiadająca udziałowi
 * metrażu mieszczącego się w limicie idzie na stawkę obniżoną, reszta
 * na podstawową.
 *
 * `share` to ten udział. `null` znaczy **nie wiemy**, a nie „zero" ani
 * „całość" — bez metrażu inwestycji nie da się policzyć proporcji,
 * więc kwoty VAT też nie da się podać. `reason` mówi, czego brakuje,
 * żeby ekran nie musiał zgadywać.
 */
final readonly class InvestmentVat
{
    public function __construct(
        public InvestmentType $type,
        public int $reducedRate,
        public int $standardRate,
        public ?float $limitM2,
        public ?float $areaM2,
        /** Udział kwoty objętej stawką obniżoną, 0..1. `null` = nieznany. */
        public ?float $share,
        public ?string $reason,
    ) {
    }

    /** Czy inwestycja przekracza limit, czyli czy w ogóle coś dzielimy. */
    public function isSplit(): bool
    {
        return $this->share !== null && $this->share < 1.0;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'reduced_rate' => $this->reducedRate,
            'standard_rate' => $this->standardRate,
            'limit_m2' => $this->limitM2,
            'area_m2' => $this->areaM2,
            'share' => $this->share,
            'is_split' => $this->isSplit(),
            'reason' => $this->reason,
        ];
    }
}
