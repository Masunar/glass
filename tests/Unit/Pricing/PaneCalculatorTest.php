<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Enum\MinPriceCheck;
use App\Enum\SurchargeMode;
use PHPUnit\Framework\TestCase;
use App\DTO\Pricing\PaneSpecification;
use App\DTO\Pricing\PricingParameters;
use App\Services\Pricing\PaneCalculator;

/**
 * Wycena formatki na wartościach z dokumentacji.
 *
 * Test jest jednostkowy, bez bazy, bo kalkulator jest czystą funkcją.
 * To celowe: cena musi dać się odtworzyć z samych parametrów, także
 * wtedy, gdy odtwarzamy wycenę sprzed roku.
 *
 * Cena bazowa w przykładach: float 8 mm, cena zakupu 52 zł/m²,
 * współczynnik 5,0 → 260,00 zł/m².
 */
class PaneCalculatorTest extends TestCase
{
    private const PRICE_PER_M2 = '260.00';

    private PaneCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PaneCalculator();
    }

    private function parameters(
        SurchargeMode $surchargeMode = SurchargeMode::CUMULATIVE,
        MinPriceCheck $minPriceCheck = MinPriceCheck::AFTER_SURCHARGES,
    ): PricingParameters {
        return new PricingParameters(
            minBillableTemperedM2: 0.4,
            minBillableUntemperedM2: 0.1,
            oversizeThresholdM2: 4.0,
            oversizeSurchargePercent: 25.0,
            shapeSurchargePercent: 35.0,
            minPanePrice: 60.0,
            minPaneSurchargePercent: 50.0,
            surchargeMode: $surchargeMode,
            minPriceCheck: $minPriceCheck,
        );
    }

    public function test_metr_kwadratowy_liczy_sie_wprost(): void
    {
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 1000, heightMm: 1000),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        $this->assertSame('260.00', $quote->net);
        $this->assertSame(1.0, $quote->billableSquareMeters);
    }

    public function test_ilosc_mnozy_powierzchnie(): void
    {
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 1000, heightMm: 1000, quantity: 3),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        $this->assertSame('780.00', $quote->net);
        $this->assertSame(3.0, $quote->billableSquareMeters);
    }

    public function test_formatka_ponizej_progu_ceny_dostaje_doplate(): void
    {
        // 0,5 × 0,4 = 0,2 m² → 52,00 zł → poniżej progu 60 → × 1,5.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 500, heightMm: 400),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        $this->assertSame('78.00', $quote->net);
        $this->assertContains('min_price', array_column(
            array_map(static fn($step) => $step->toArray(), $quote->steps),
            'code',
        ));
    }

    public function test_hartowanie_podnosi_powierzchnie_do_minimum(): void
    {
        // 0,3 × 0,3 = 0,09 m², ale hartownia liczy nie mniej niż 0,4 m².
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 300, heightMm: 300, isTempered: true),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        $this->assertSame(0.4, $quote->billableSquareMeters);
        $this->assertSame('104.00', $quote->net);
    }

    public function test_formatka_niehartowana_ma_nizsze_minimum(): void
    {
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 200, heightMm: 200),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        // 0,04 m² podniesione do 0,1 m² → 26,00 → poniżej progu → × 1,5.
        $this->assertSame(0.1, $quote->billableSquareMeters);
        $this->assertSame('39.00', $quote->net);
    }

    public function test_gabaryt_powyzej_progu_dolicza_doplate(): void
    {
        // 2,5 × 2,0 = 5 m² > 4 m² → 1300,00 × 1,25.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 2500, heightMm: 2000),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        $this->assertSame('1625.00', $quote->net);
    }

    public function test_prog_gabarytu_liczy_sie_od_pojedynczej_formatki(): void
    {
        // Cztery formatki po 1 m² to 4 m² pozycji, ale żadna nie jest
        // wielkogabarytowa — dopłata nie należy się od sumy.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 1000, heightMm: 1000, quantity: 4),
            self::PRICE_PER_M2,
            $this->parameters(),
        );

        $this->assertSame('1040.00', $quote->net);
    }

    public function test_ksztalt_i_gabaryt_kumuluja_sie(): void
    {
        // 1300,00 × 1,35 × 1,25 = 2193,75.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 2500, heightMm: 2000, isIrregularShape: true),
            self::PRICE_PER_M2,
            $this->parameters(SurchargeMode::CUMULATIVE),
        );

        $this->assertSame('2193.75', $quote->net);
    }

    public function test_w_trybie_najwyzszej_doplaty_obowiazuje_tylko_jedna(): void
    {
        // 1300,00 × 1,35 — gabaryt 25% ustępuje kształtowi 35%.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 2500, heightMm: 2000, isIrregularShape: true),
            self::PRICE_PER_M2,
            $this->parameters(SurchargeMode::HIGHEST_ONLY),
        );

        $this->assertSame('1755.00', $quote->net);

        $codes = array_column(
            array_map(static fn($step) => $step->toArray(), $quote->steps),
            'code',
        );

        $this->assertContains('shape', $codes);
        $this->assertNotContains('oversize', $codes);
    }

    public function test_prog_minimalnej_ceny_sprawdzany_przed_doplatami(): void
    {
        // 52,00 poniżej progu → × 1,5 = 78,00 → × 1,35 = 105,30.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 500, heightMm: 400, isIrregularShape: true),
            self::PRICE_PER_M2,
            $this->parameters(minPriceCheck: MinPriceCheck::BEFORE_SURCHARGES),
        );

        $this->assertSame('105.30', $quote->net);
    }

    public function test_prog_minimalnej_ceny_sprawdzany_po_doplatach(): void
    {
        // 52,00 × 1,35 = 70,20 — próg 60 zł zostaje przekroczony,
        // więc dopłata do minimum w ogóle nie wchodzi.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 500, heightMm: 400, isIrregularShape: true),
            self::PRICE_PER_M2,
            $this->parameters(minPriceCheck: MinPriceCheck::AFTER_SURCHARGES),
        );

        $this->assertSame('70.20', $quote->net);

        $codes = array_column(
            array_map(static fn($step) => $step->toArray(), $quote->steps),
            'code',
        );

        $this->assertNotContains('min_price', $codes);
    }

    public function test_procesy_licza_sie_od_metrow_biezacych(): void
    {
        // Obwód 1×1 m to 4 mb; szlifowanie po 6 zł/mb daje 24,00.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 1000, heightMm: 1000),
            self::PRICE_PER_M2,
            $this->parameters(),
            [['label' => 'Szlifowanie', 'net_price_per_running_meter' => '6.00']],
        );

        $this->assertSame(4.0, $quote->runningMeters);
        $this->assertSame('284.00', $quote->net);
    }

    public function test_proces_nie_wchodzi_do_podstawy_doplat(): void
    {
        // Dopłata za kształt liczy się od materiału, nie od materiału
        // z obróbką: 260 × 1,35 = 351,00, plus 24,00 za szlifowanie.
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 1000, heightMm: 1000, isIrregularShape: true),
            self::PRICE_PER_M2,
            $this->parameters(),
            [['label' => 'Szlifowanie', 'net_price_per_running_meter' => '6.00']],
        );

        $this->assertSame('375.00', $quote->net);
    }

    public function test_wycena_pokazuje_kazdy_krok(): void
    {
        $quote = $this->calculator->calculate(
            new PaneSpecification(widthMm: 2500, heightMm: 2000, isIrregularShape: true),
            self::PRICE_PER_M2,
            $this->parameters(),
            [['label' => 'Szlifowanie', 'net_price_per_running_meter' => '6.00']],
        );

        $codes = array_column(
            array_map(static fn($step) => $step->toArray(), $quote->steps),
            'code',
        );

        $this->assertSame(['area', 'base', 'shape', 'oversize', 'process'], $codes);
    }
}
