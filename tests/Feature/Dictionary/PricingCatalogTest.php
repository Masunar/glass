<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Vehicle;
use App\Models\Process;
use App\Models\PriceSection;
use App\Models\InvoiceType;
use App\Models\GlobalParameter;
use Database\Seeders\Core\RoleSeeder;
use Database\Seeders\Core\ProcessSeeder;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\DictionarySeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Database\Seeders\Core\GlobalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PricingCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new LocationSeeder())->run();
        (new RoleSeeder())->run();
        (new ProcessSeeder())->run();
        (new PriceSectionSeeder())->run();
        (new GlobalParameterSeeder())->run();
        (new DictionarySeeder())->run();
    }

    public function test_kazda_sekcja_asortymentu_ma_jedna_domyslna_sekcje_cenowa(): void
    {
        foreach (Section::cases() as $section) {
            $defaults = PriceSection::query()
                ->where('section', $section->value)
                ->where('is_default', true)
                ->count();

            $this->assertSame(
                1,
                $defaults,
                sprintf('Sekcja "%s" ma %d domyślnych poziomów cenowych zamiast jednego.', $section->value, $defaults),
            );
        }
    }

    /**
     * Zasada z dokumentacji: im tańsza sekcja cenowa, tym mniejszy rabat
     * dodatkowy może udzielić handlowiec. Klient na najniższym poziomie
     * ma limit zerowy, bo już ma najniższą cenę.
     */
    public function test_limit_rabatu_nie_rosnie_wraz_z_taniejacym_poziomem_cenowym(): void
    {
        $sections = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->with('discountLimits.role')
            ->orderBy('position')
            ->get();

        $this->assertGreaterThan(1, $sections->count());

        $previous = null;

        foreach ($sections as $section) {
            $limit = $section->discountLimits
                ->firstWhere('role.name', RoleSeeder::SALES)
                ?->max_discount_percent;

            $this->assertNotNull($limit, sprintf('Sekcja "%s" nie ma limitu dla handlowca.', $section->name));

            if ($previous !== null) {
                $this->assertLessThanOrEqual(
                    (float) $previous,
                    (float) $limit,
                    sprintf('Limit rabatu rośnie na tańszym poziomie "%s".', $section->name),
                );
            }

            $previous = $limit;
        }
    }

    public function test_najtanszy_poziom_cenowy_szkla_nie_daje_juz_rabatu(): void
    {
        $cheapest = PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->with('discountLimits.role')
            ->orderByDesc('position')
            ->firstOrFail();

        $limit = $cheapest->discountLimits->firstWhere('role.name', RoleSeeder::SALES)?->max_discount_percent;

        $this->assertSame('0.00', $limit);
    }

    public function test_katalog_procesow_ma_czternascie_pozycji_z_unikalnymi_kodami(): void
    {
        $processes = Process::query()->get();

        $this->assertCount(14, $processes);
        $this->assertSame(14, $processes->pluck('code')->unique()->count());
        $this->assertSame(14, $processes->pluck('legacy_id')->unique()->count());
    }

    public function test_hartowanie_jest_jedynym_procesem_podzlecanym(): void
    {
        $subcontracted = Process::query()->where('is_subcontracted', true)->pluck('code');

        $this->assertSame(['H'], $subcontracted->all());
    }

    public function test_parametry_wyceny_maja_udokumentowane_wartosci(): void
    {
        $this->assertSame(0.4, GlobalParameter::number('min_billable_m2_tempered'));
        $this->assertSame(0.1, GlobalParameter::number('min_billable_m2_untempered'));
        $this->assertSame(4.0, GlobalParameter::number('oversize_threshold_m2'));
        $this->assertSame(25.0, GlobalParameter::number('oversize_surcharge_percent'));
        $this->assertSame(35.0, GlobalParameter::number('shape_surcharge_percent'));
        $this->assertSame(60.0, GlobalParameter::number('min_pane_price'));
        $this->assertSame(50.0, GlobalParameter::number('min_pane_surcharge_percent'));
    }

    /**
     * Ważność oferty istniała w starym systemie w dwóch miejscach: pole
     * liczbowe mówiło 10 dni, a tekst drukowany klientowi 7. Tekst jest
     * teraz szablonem z podstawianą zmienną, więc rozjazd jest niemożliwy.
     */
    public function test_tekst_o_waznosci_oferty_nie_powtarza_liczby(): void
    {
        $text = GlobalParameter::value('offer_validity_text');

        $this->assertNotNull($text);
        $this->assertStringContainsString('{{offer_validity_days}}', $text);
        $this->assertStringNotContainsString('7 dni', $text);
    }

    public function test_nie_ma_typu_faktury_ze_stawka_vat_spoza_polskiego_systemu(): void
    {
        $allowed = [0, 5, 8, 23];
        $rates = InvoiceType::query()->pluck('vat_rate')->unique()->all();

        foreach ($rates as $rate) {
            $this->assertContains(
                $rate,
                $allowed,
                sprintf('Stawka VAT %d%% nie występuje w polskim systemie.', $rate),
            );
        }
    }

    public function test_flota_ma_udokumentowane_ladownosci(): void
    {
        $payloads = Vehicle::query()->pluck('payload_kg', 'name')->all();

        $this->assertSame(1100, $payloads['Hyundai']);
        $this->assertSame(850, $payloads['Mercedes']);
        $this->assertSame(1200, $payloads['Fiat']);
        $this->assertSame(700, $payloads['Ford']);
    }
}
