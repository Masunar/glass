<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\MinPriceCheck;
use App\Enum\SurchargeMode;
use App\DTO\Pricing\PricingParameters;
use App\Services\GlobalParameterService;
use Database\Seeders\Core\GlobalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Kalkulator dostaje parametry jako wartość, ale ktoś musi je zdjąć
 * z bazy — i zdjąć te, które obowiązywały w dniu wyceny. Bez tego
 * oferta sprzed roku przeliczyłaby się dzisiejszą dopłatą.
 */
class PricingParametersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new GlobalParameterSeeder())->run();
    }

    public function test_parametry_czytaja_sie_z_bazy(): void
    {
        $parameters = PricingParameters::effective();

        $this->assertSame(0.4, $parameters->minBillableTemperedM2);
        $this->assertSame(0.1, $parameters->minBillableUntemperedM2);
        $this->assertSame(4.0, $parameters->oversizeThresholdM2);
        $this->assertSame(25.0, $parameters->oversizeSurchargePercent);
        $this->assertSame(35.0, $parameters->shapeSurchargePercent);
        $this->assertSame(60.0, $parameters->minPanePrice);
        $this->assertSame(50.0, $parameters->minPaneSurchargePercent);
        $this->assertSame(SurchargeMode::CUMULATIVE, $parameters->surchargeMode);
        $this->assertSame(MinPriceCheck::AFTER_SURCHARGES, $parameters->minPriceCheck);
    }

    public function test_wycena_historyczna_widzi_stara_wartosc(): void
    {
        Carbon::setTestNow(Carbon::today());

        $errors = (new GlobalParameterService())->update(['shape_surcharge_percent' => '40']);
        $this->assertSame([], $errors);

        $this->assertSame(40.0, PricingParameters::effective()->shapeSurchargePercent);

        // Poprzednia wersja została zamknięta wczorajszą datą, więc oferta
        // wystawiona wczoraj nadal przelicza się starą dopłatą.
        $this->assertSame(
            35.0,
            PricingParameters::effective(Carbon::yesterday())->shapeSurchargePercent,
        );

        Carbon::setTestNow();
    }

    public function test_niedozwolona_wartosc_parametru_wyboru_jest_odrzucana(): void
    {
        $errors = (new GlobalParameterService())->update(['surcharge_mode' => 'srednia']);

        $this->assertArrayHasKey('surcharge_mode', $errors);
        $this->assertSame(
            SurchargeMode::CUMULATIVE,
            PricingParameters::effective()->surchargeMode,
        );
    }

    public function test_zmiana_trybu_doplat_dziala(): void
    {
        $errors = (new GlobalParameterService())->update([
            'surcharge_mode' => SurchargeMode::HIGHEST_ONLY->value,
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(
            SurchargeMode::HIGHEST_ONLY,
            PricingParameters::effective()->surchargeMode,
        );
    }
}
