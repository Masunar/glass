<?php

declare(strict_types=1);

namespace App\DTO\Pricing;

use Carbon\Carbon;
use App\Enum\MinPriceCheck;
use App\Enum\SurchargeMode;
use App\Models\GlobalParameter;

/**
 * Parametry wzoru wyceny w jednym miejscu.
 *
 * Kalkulator dostaje je jako wartość, a nie sięga po nie sam — dzięki
 * temu jest czystą funkcją i daje się przetestować bez bazy, a wycena
 * historyczna liczy się parametrami z dnia, w którym powstała.
 */
final readonly class PricingParameters
{
    public function __construct(
        public float $minBillableTemperedM2,
        public float $minBillableUntemperedM2,
        public float $oversizeThresholdM2,
        public float $oversizeSurchargePercent,
        public float $shapeSurchargePercent,
        public float $minPanePrice,
        public float $minPaneSurchargePercent,
        public SurchargeMode $surchargeMode,
        public MinPriceCheck $minPriceCheck,
    ) {}

    public static function effective(?Carbon $date = null): self
    {
        return new self(
            minBillableTemperedM2: GlobalParameter::number('min_billable_m2_tempered', $date) ?? 0.4,
            minBillableUntemperedM2: GlobalParameter::number('min_billable_m2_untempered', $date) ?? 0.1,
            oversizeThresholdM2: GlobalParameter::number('oversize_threshold_m2', $date) ?? 4.0,
            oversizeSurchargePercent: GlobalParameter::number('oversize_surcharge_percent', $date) ?? 25.0,
            shapeSurchargePercent: GlobalParameter::number('shape_surcharge_percent', $date) ?? 35.0,
            minPanePrice: GlobalParameter::number('min_pane_price', $date) ?? 60.0,
            minPaneSurchargePercent: GlobalParameter::number('min_pane_surcharge_percent', $date) ?? 50.0,
            surchargeMode: SurchargeMode::tryFrom(
                (string) GlobalParameter::value('surcharge_mode', $date),
            ) ?? SurchargeMode::CUMULATIVE,
            minPriceCheck: MinPriceCheck::tryFrom(
                (string) GlobalParameter::value('min_price_check', $date),
            ) ?? MinPriceCheck::AFTER_SURCHARGES,
        );
    }
}
