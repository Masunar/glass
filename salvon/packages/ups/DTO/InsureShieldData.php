<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

/**
 * UPS Capital InsureShield cargo insurance.
 * Used in tandem with DeclaredValue when the declared value exceeds the
 * carrier's built-in liability ceiling (typically $100 / ~500 PLN built-in,
 * up to ~$50 000 via DeclaredValue alone; above that requires InsureShield
 * Cargo arranged on the UPS Capital contract).
 */
final readonly class InsureShieldData
{
    public function __construct(
        public float  $insuredValue,
        public string $currencyCode = 'PLN',
        public ?string $coverageType = null,
    ) {}
}
