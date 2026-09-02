<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Product;
use App\Enum\PriceSource;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Models\PriceSection;
use App\Models\ContractorPrice;
use App\Services\PriceListService;
use Database\Seeders\Core\RoleSeeder;
use App\Models\ContractorPriceSection;
use App\Services\Pricing\PriceResolver;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Poziomy 2 i 3 ustalania ceny.
 *
 * Cztery nakładające się poziomy to miejsce, w którym system może
 * zacząć kłamać — stąd testy pilnują nie tylko kwoty, ale i śladu
 * wyliczenia, bo bez niego handlowiec nie uzasadni ceny klientowi.
 */
class ContractorPriceTest extends TestCase
{
    use RefreshDatabase;

    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();

        $this->resolver = new PriceResolver();

        // float 8mm: cena zakupu 52,00. Detaliczny podstawowy x 4,0 = 208,00,
        // Biznesowy strefa 3 x 2,2 = 114,40.
        $service = new PriceListService();
        $product = $this->product();

        $service->update([
            [
                'product_id' => $product->id,
                'price_section_id' => $this->section('Detaliczny podstawowy')->id,
                'coefficient' => '4.0',
            ],
            [
                'product_id' => $product->id,
                'price_section_id' => $this->section('Biznesowy strefa 3')->id,
                'coefficient' => '2.2',
            ],
        ]);
    }

    private function product(): Product
    {
        /** @var Product */
        return Product::query()->where('name', 'float 8mm')->firstOrFail();
    }

    private function section(string $name): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', $name)
            ->firstOrFail();
    }

    private function contractor(): Contractor
    {
        /** @var Contractor */
        return Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'STECKO MEBLE sp. z o.o. sp.k.',
            'short_name' => 'Stecko Meble',
            'tax_id' => '8522347066',
        ]);
    }

    public function test_bez_przypisanej_sekcji_obowiazuje_domyslna(): void
    {
        $price = $this->resolver->forContractor($this->product(), $this->contractor());

        $this->assertSame('208.00', $price->netPrice);
        $this->assertSame('Detaliczny podstawowy', $price->priceSectionName);
    }

    public function test_sekcja_kontrahenta_zmienia_cene(): void
    {
        $contractor = $this->contractor();

        ContractorPriceSection::query()->create([
            'contractor_id' => $contractor->id,
            'section' => Section::GLASS->value,
            'price_section_id' => $this->section('Biznesowy strefa 3')->id,
        ]);

        $price = $this->resolver->forContractor($this->product(), $contractor->refresh());

        $this->assertSame('114.40', $price->netPrice);
        $this->assertSame('Biznesowy strefa 3', $price->priceSectionName);
    }

    public function test_cena_indywidualna_jest_nadrzedna(): void
    {
        $contractor = $this->contractor();

        ContractorPriceSection::query()->create([
            'contractor_id' => $contractor->id,
            'section' => Section::GLASS->value,
            'price_section_id' => $this->section('Biznesowy strefa 3')->id,
        ]);

        ContractorPrice::query()->create([
            'contractor_id' => $contractor->id,
            'product_id' => $this->product()->id,
            'net_price' => '99.00',
            'valid_from' => Carbon::today()->subDay(),
        ]);

        $price = $this->resolver->forContractor($this->product(), $contractor->refresh());

        $this->assertSame('99.00', $price->netPrice);
        $this->assertSame(PriceSource::INDIVIDUAL, $price->source);
    }

    public function test_slad_pokazuje_kazdy_poziom(): void
    {
        $contractor = $this->contractor();

        ContractorPriceSection::query()->create([
            'contractor_id' => $contractor->id,
            'section' => Section::GLASS->value,
            'price_section_id' => $this->section('Biznesowy strefa 3')->id,
        ]);

        ContractorPrice::query()->create([
            'contractor_id' => $contractor->id,
            'product_id' => $this->product()->id,
            'net_price' => '99.00',
            'valid_from' => Carbon::today(),
        ]);

        $price = $this->resolver->forContractor($this->product(), $contractor->refresh());
        $codes = array_column(
            array_map(static fn($step) => $step->toArray(), $price->steps),
            'code',
        );

        $this->assertSame(['catalogue', 'individual'], $codes);
        $this->assertSame('114.40', $price->steps[0]->value);
        $this->assertSame('99.00', $price->steps[1]->value);
    }

    public function test_cena_indywidualna_wygasa_z_data(): void
    {
        $contractor = $this->contractor();

        ContractorPrice::query()->create([
            'contractor_id' => $contractor->id,
            'product_id' => $this->product()->id,
            'net_price' => '99.00',
            'valid_from' => Carbon::today()->subMonth(),
            'valid_to' => Carbon::today()->subDay(),
        ]);

        // Wczoraj promocja jeszcze obowiazywala, dzisiaj juz nie.
        $this->assertSame(
            '99.00',
            $this->resolver
                ->forContractor($this->product(), $contractor, Carbon::yesterday())
                ->netPrice,
        );
        $this->assertSame(
            '208.00',
            $this->resolver->forContractor($this->product(), $contractor)->netPrice,
        );
    }

    public function test_bez_kontrahenta_dziala_sama_cena_katalogowa(): void
    {
        $price = $this->resolver->forContractor($this->product());

        $this->assertSame('208.00', $price->netPrice);
        $this->assertSame(PriceSource::COMPUTED, $price->source);
    }

    public function test_sekcja_kontrahenta_dotyczy_wlasciwego_asortymentu(): void
    {
        $contractor = $this->contractor();

        // Przypisanie dla Uslug nie moze wplywac na cene szkla.
        ContractorPriceSection::query()->create([
            'contractor_id' => $contractor->id,
            'section' => Section::SERVICES->value,
            'price_section_id' => PriceSection::query()
                ->where('section', Section::SERVICES->value)
                ->firstOrFail()->id,
        ]);

        $price = $this->resolver->forContractor($this->product(), $contractor->refresh());

        $this->assertSame('Detaliczny podstawowy', $price->priceSectionName);
    }

    public function test_nazwa_do_list_uzywa_skrotu(): void
    {
        $this->assertSame('Stecko Meble', $this->contractor()->displayName());
    }
}
