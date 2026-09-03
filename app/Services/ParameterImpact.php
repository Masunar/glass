<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Enum\SurchargeMode;
use App\Enum\MinPriceCheck;
use App\DTO\Pricing\PaneSpecification;
use App\DTO\Pricing\PricingParameters;
use App\Services\Pricing\PaneCalculator;

/**
 * O ile zmiana parametru ruszy ceny.
 *
 * Ekran parametrów pokazywał samą liczbę do wpisania. Nikt nie wiedział,
 * czy podniesienie dopłaty za kształt z 30 na 35 % to ruch o złotówkę,
 * czy o jedną piątą wartości oferty — a to jest jedyne pytanie, jakie
 * ktoś sobie przy tym ekranie zadaje.
 *
 * Wynik podawany jest **wyłącznie w procentach**. Kwota zależałaby od
 * ceny szkła, a ta jest inna dla każdego produktu; procent nie zależy.
 * Dlatego cena odniesienia poniżej jest jednostką rachunkową, nie
 * kwotą do pokazania.
 */
final readonly class ParameterImpact
{
    /** Cena bazowa wyłącznie do policzenia stosunku — nigdy nie wyświetlana. */
    private const REFERENCE_PRICE = '100.00';

    public function __construct(
        private PaneCalculator $calculator = new PaneCalculator(),
    ) {
    }

    /**
     * Formatki odniesienia. Każda uruchamia inny fragment wzoru, więc
     * razem pokrywają wszystkie parametry cenowe — bez nich zmiana
     * dopłaty za gabaryt dałaby zero, bo mała formatka progu nie
     * przekracza.
     *
     * @return array<string, array{label: string, pane: PaneSpecification}>
     */
    private function samples(): array
    {
        return [
            'small_tempered' => [
                'label' => 'mała formatka hartowana 0,30 m²',
                'pane' => new PaneSpecification(500, 600, isTempered: true),
            ],
            'small_plain' => [
                'label' => 'mała formatka niehartowana 0,30 m²',
                'pane' => new PaneSpecification(500, 600),
            ],
            'oversize' => [
                'label' => 'duża tafla 5,00 m²',
                'pane' => new PaneSpecification(2500, 2000),
            ],
            'irregular' => [
                'label' => 'formatka nieregularna 0,64 m²',
                'pane' => new PaneSpecification(800, 800, isIrregularShape: true),
            ],
            'irregular_oversize' => [
                'label' => 'nieregularna tafla 5,00 m²',
                'pane' => new PaneSpecification(2500, 2000, isIrregularShape: true),
            ],
        ];
    }

    /**
     * Która formatka najlepiej pokazuje skutek danego parametru.
     * Parametr spoza tej mapy nie rusza cen w ogóle.
     */
    private const SAMPLE_FOR = [
        'min_billable_m2_tempered' => 'small_tempered',
        'min_billable_m2_untempered' => 'small_plain',
        'oversize_threshold_m2' => 'oversize',
        'oversize_surcharge_percent' => 'oversize',
        'shape_surcharge_percent' => 'irregular',
        'min_pane_price' => 'small_plain',
        'min_pane_surcharge_percent' => 'small_plain',
        // Kolejność mnożników widać dopiero tam, gdzie obie dopłaty
        // spotykają się na jednej formatce.
        'surcharge_mode' => 'irregular_oversize',
        'min_price_check' => 'small_plain',
    ];

    /**
     * @param array<string, string|null> $proposed wartości wpisane, jeszcze niezapisane
     * @return array<string, mixed>
     */
    public function preview(array $proposed, ?Carbon $date = null): array
    {
        $current = PricingParameters::effective($date);
        $next = $this->overlay($current, $proposed);

        $perParameter = [];

        foreach ($proposed as $key => $value) {
            $sampleKey = self::SAMPLE_FOR[$key] ?? null;

            if ($sampleKey === null) {
                // Teksty ofertowe i limity wymiarów nie wchodzą do wzoru.
                $perParameter[] = ['key' => $key, 'percent' => null, 'sample' => null];
                continue;
            }

            $sample = $this->samples()[$sampleKey];
            $single = $this->overlay($current, [$key => $value]);

            $perParameter[] = [
                'key' => $key,
                'percent' => $this->delta($sample['pane'], $current, $single),
                'sample' => $sample['label'],
            ];
        }

        return [
            'parameters' => $perParameter,
            'average_percent' => $this->averageDelta($current, $next),
        ];
    }

    /**
     * Średnia po wszystkich formatkach odniesienia. Mówi o kierunku
     * i rzędzie wielkości, nie o konkretnej ofercie — tych jeszcze nie
     * ma czym policzyć, bo moduł zleceń nie istnieje.
     */
    private function averageDelta(PricingParameters $before, PricingParameters $after): ?float
    {
        $deltas = [];

        foreach ($this->samples() as $sample) {
            $delta = $this->delta($sample['pane'], $before, $after);

            if ($delta !== null) {
                $deltas[] = $delta;
            }
        }

        if ($deltas === []) {
            return null;
        }

        return round(array_sum($deltas) / count($deltas), 1);
    }

    private function delta(
        PaneSpecification $pane,
        PricingParameters $before,
        PricingParameters $after,
    ): ?float {
        $old = (float) $this->calculator->calculate($pane, self::REFERENCE_PRICE, $before)->net;
        $new = (float) $this->calculator->calculate($pane, self::REFERENCE_PRICE, $after)->net;

        if ($old <= 0.0) {
            return null;
        }

        return round((($new - $old) / $old) * 100, 1);
    }

    /**
     * Nakłada niezapisane wartości na obowiązujące. Wartość, której nie
     * da się odczytać jako liczby, zostaje bez zmian — ekran i tak jej
     * nie zapisze, a wzór nie może się na niej wywrócić.
     *
     * @param array<string, string|null> $values
     */
    private function overlay(PricingParameters $base, array $values): PricingParameters
    {
        $number = static function (string $key, float $fallback) use ($values): float {
            if (!array_key_exists($key, $values)) {
                return $fallback;
            }

            $value = $values[$key];

            return is_numeric($value) ? (float) $value : $fallback;
        };

        return new PricingParameters(
            minBillableTemperedM2: $number('min_billable_m2_tempered', $base->minBillableTemperedM2),
            minBillableUntemperedM2: $number('min_billable_m2_untempered', $base->minBillableUntemperedM2),
            oversizeThresholdM2: $number('oversize_threshold_m2', $base->oversizeThresholdM2),
            oversizeSurchargePercent: $number('oversize_surcharge_percent', $base->oversizeSurchargePercent),
            shapeSurchargePercent: $number('shape_surcharge_percent', $base->shapeSurchargePercent),
            minPanePrice: $number('min_pane_price', $base->minPanePrice),
            minPaneSurchargePercent: $number('min_pane_surcharge_percent', $base->minPaneSurchargePercent),
            surchargeMode: SurchargeMode::tryFrom((string) ($values['surcharge_mode'] ?? ''))
                ?? $base->surchargeMode,
            minPriceCheck: MinPriceCheck::tryFrom((string) ($values['min_price_check'] ?? ''))
                ?? $base->minPriceCheck,
        );
    }
}
