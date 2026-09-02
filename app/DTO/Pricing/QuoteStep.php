<?php

declare(strict_types=1);

namespace App\DTO\Pricing;

/**
 * Jeden krok wyliczenia ceny.
 *
 * Ślad kroków jest wymaganiem, nie ozdobą: przy czterech nakładających
 * się poziomach ceny i trzech dopłatach handlowiec patrzy na kwotę
 * i nie wie, skąd się wzięła. Bez tego negocjacje z klientem odbywają
 * się na wyczucie, a reklamacje cenowe są nie do rozstrzygnięcia.
 */
final readonly class QuoteStep
{
    public function __construct(
        public string $code,
        public string $label,
        public string $value,
        public ?string $detail = null,
    ) {}

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'value' => $this->value,
            'detail' => $this->detail,
        ];
    }
}
