<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Product;
use App\Enum\PriceSource;
use App\Models\PriceSection;
use App\Models\PriceListItem;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use App\Enum\PriceUnavailableReason;
use Database\Seeders\Core\RoleSeeder;
use App\Services\Pricing\PriceResolver;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Cennik i pierwszy poziom ustalania ceny.
 *
 * Wartości oczekiwane pochodzą z rekonstrukcji macierzy cennika starego
 * systemu, potwierdzonej co do grosza słownikiem materiałów
 * (`50-cennik.md` §4).
 */
class PriceListTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $service;

    private PriceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();

        $this->service = new PriceListService();
        $this->resolver = new PriceResolver();
    }

    private function product(string $name): Product
    {
        /** @var Product */
        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function section(string $name): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', Section::GLASS->value)
            ->where('name', $name)
            ->firstOrFail();
    }

    /**
     * Sam wzór jest sprawdzony w GlassCatalogTest. Tutaj chodzi o drogę
     * przez zapis i odczyt: czy współczynnik wpisany w cenniku wraca
     * jako cena obowiązująca przy wycenie.
     *
     * @return array<string, array{string, string, string}> nazwa produktu, współczynnik, cena
     */
    public static function documentedCells(): array
    {
        return [
            'float 2mm × 3,8' => ['float 2mm', '3.8', '83.60'],
            'float 6mm × 3,5' => ['float 6mm', '3.5', '129.50'],
            'float 8mm × 5,0' => ['float 8mm', '5.0', '260.00'],
        ];
    }

    #[DataProvider('documentedCells')]
    public function test_cena_wynika_ze_wspolczynnika(string $name, string $coefficient, string $expected): void
    {
        $product = $this->product($name);
        $section = $this->section('Detaliczny extra');

        $errors = $this->service->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => $coefficient,
        ]]);

        $this->assertSame([], $errors);

        $price = $this->resolver->catalogue($product, $section);

        $this->assertTrue($price->isAvailable());
        $this->assertSame($expected, $price->netPrice);
        $this->assertSame(PriceSource::COMPUTED, $price->source);
    }

    public function test_bez_sekcji_cenowej_uzywa_domyslnej(): void
    {
        $product = $this->product('float 8mm');
        $default = $this->section('Detaliczny podstawowy');

        $this->assertTrue((bool) $default->is_default);

        $this->service->update([[
            'product_id' => $product->id,
            'price_section_id' => $default->id,
            'coefficient' => '4.0',
        ]]);

        $price = $this->resolver->catalogue($product);

        $this->assertSame('208.00', $price->netPrice);
        $this->assertSame($default->id, $price->priceSectionId);
    }

    public function test_produkt_bez_pozycji_cennika_nie_kosztuje_zera(): void
    {
        $price = $this->resolver->catalogue($this->product('float 8mm'));

        $this->assertFalse($price->isAvailable());
        $this->assertNull($price->netPrice);
        $this->assertSame(PriceUnavailableReason::NO_PRICE_LIST_ITEM, $price->unavailableReason);
    }

    public function test_produkt_bez_ceny_zakupu_nie_dostaje_wymyslonej_ceny(): void
    {
        // Lustra nie mają udokumentowanej ceny zakupu, więc współczynnik
        // nie ma czego pomnożyć. Wynik to brak ceny z powodem, nie zero.
        $product = $this->product('lustro AGC 4mm');
        $section = $this->section('Detaliczny extra');

        $errors = $this->service->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => '3.0',
        ]]);

        $this->assertSame([], $errors);

        $price = $this->resolver->catalogue($product, $section);

        $this->assertFalse($price->isAvailable());
        $this->assertSame(PriceUnavailableReason::NO_PRICE, $price->unavailableReason);
    }

    public function test_cena_reczna_wygrywa_ze_wspolczynnikiem(): void
    {
        $product = $this->product('float 8mm');
        $section = $this->section('Detaliczny extra');

        $this->service->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => '5.0',
            'manual_net_price' => '265.00',
        ]]);

        $price = $this->resolver->catalogue($product, $section);

        $this->assertSame('265.00', $price->netPrice);
        $this->assertSame(PriceSource::MANUAL, $price->source);
    }

    public function test_nowa_dostawa_nie_przelicza_cennika_po_cichu(): void
    {
        $product = $this->product('float 8mm');
        $section = $this->section('Detaliczny extra');

        $this->service->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => '5.0',
        ]]);

        // Dostawa po wyższej cenie: cennik zostaje, ale rozjazd jest widoczny.
        PurchasePrice::query()->where('product_id', $product->id)->update([
            'valid_to' => Carbon::today()->subDay(),
        ]);
        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '60.00',
            'source' => PurchasePriceSource::DELIVERY->value,
            'valid_from' => Carbon::today(),
        ]);

        $price = $this->resolver->catalogue($product->refresh(), $section);

        $this->assertSame('260.00', $price->netPrice);
        $this->assertSame('300.00', $price->recomputedNetPrice);
        $this->assertTrue($price->isStale());
    }

    public function test_zmiana_wspolczynnika_zaklada_nowa_wersje(): void
    {
        $product = $this->product('float 8mm');
        $section = $this->section('Detaliczny extra');
        $lastWeek = Carbon::today()->subWeek();

        $this->service->update(
            [['product_id' => $product->id, 'price_section_id' => $section->id, 'coefficient' => '5.0']],
            $lastWeek,
        );

        $this->service->update(
            [['product_id' => $product->id, 'price_section_id' => $section->id, 'coefficient' => '4.0']],
            Carbon::today(),
        );

        $versions = PriceListItem::query()
            ->where('product_id', $product->id)
            ->where('price_section_id', $section->id)
            ->orderBy('valid_from')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame('260.00', $versions[0]->computed_net_price);
        $this->assertSame('208.00', $versions[1]->computed_net_price);

        // Oferta sprzed tygodnia liczy się starą ceną.
        $this->assertSame(
            '260.00',
            $this->resolver->catalogue($product, $section, $lastWeek)->netPrice,
        );
        $this->assertSame(
            '208.00',
            $this->resolver->catalogue($product, $section)->netPrice,
        );
    }

    public function test_wyczyszczenie_komorki_zamyka_wersje_zamiast_kasowac(): void
    {
        $product = $this->product('float 8mm');
        $section = $this->section('Detaliczny extra');
        $lastWeek = Carbon::today()->subWeek();

        $this->service->update(
            [['product_id' => $product->id, 'price_section_id' => $section->id, 'coefficient' => '5.0']],
            $lastWeek,
        );

        $this->service->update(
            [['product_id' => $product->id, 'price_section_id' => $section->id, 'coefficient' => null]],
            Carbon::today(),
        );

        $this->assertSame(1, PriceListItem::query()->count());
        $this->assertFalse($this->resolver->catalogue($product, $section)->isAvailable());
        $this->assertSame(
            '260.00',
            $this->resolver->catalogue($product, $section, $lastWeek)->netPrice,
        );
    }

    public function test_macierz_pokazuje_cene_zakupu_i_marze(): void
    {
        $product = $this->product('float 8mm');
        $section = $this->section('Detaliczny extra');

        $this->service->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => '5.0',
        ]]);

        $matrix = $this->service->matrix(Section::GLASS, $this->floatGroupId());

        $this->assertSame(Section::GLASS->value, $matrix['section']);
        $this->assertCount(6, $matrix['columns']);

        $row = collect($matrix['rows'])->firstWhere('product_id', $product->id);

        $this->assertNotNull($row);
        $this->assertSame('52.00', $row['purchase_net_price']);
        $this->assertSame('260.00', $row['cells'][(string) $section->id]['net_price']);
        $this->assertSame(80.0, $row['cells'][(string) $section->id]['margin_percent']);
    }

    public function test_wiersze_ida_od_najcienszej_szyby(): void
    {
        $matrix = $this->service->matrix(Section::GLASS, $this->floatGroupId());
        $thicknesses = array_column($matrix['rows'], 'thickness_mm');

        $sorted = $thicknesses;
        sort($sorted);

        $this->assertSame($sorted, $thicknesses);
    }

    public function test_wspolczynnik_musi_byc_dodatni(): void
    {
        $errors = $this->service->update([[
            'product_id' => $this->product('float 8mm')->id,
            'price_section_id' => $this->section('Detaliczny extra')->id,
            'coefficient' => '0',
        ]]);

        $this->assertArrayHasKey('cells.0.coefficient', $errors);
        $this->assertSame(0, PriceListItem::query()->count());
    }

    private function floatGroupId(): int
    {
        return (int) $this->product('float 8mm')->product_group_id;
    }
}
