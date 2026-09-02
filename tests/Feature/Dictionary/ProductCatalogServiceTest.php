<?php

declare(strict_types=1);

namespace Tests\Feature\Dictionary;

use App\Enum\Unit;
use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use Database\Seeders\Core\RoleSeeder;
use App\Services\ProductCatalogService;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Dodawanie i edycja wierszy cennika.
 *
 * Trzy rzeczy pilnowane wprost, bo w starym systemie zawiodły:
 * słowniki nie usuwają, nazwa jest wymagana i unikalna, a cena zakupu
 * jest wersjonowana.
 */
class ProductCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductCatalogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();

        $this->service = new ProductCatalogService();
    }

    private function group(string $name, Section $section = Section::GLASS): ProductGroup
    {
        /** @var ProductGroup */
        return ProductGroup::query()
            ->where('section', $section->value)
            ->where('name', $name)
            ->firstOrFail();
    }

    public function test_nowy_produkt_szklany_dostaje_grubosc_i_cene_zakupu(): void
    {
        $result = $this->service->saveProduct([
            'product_group_id' => $this->group('OPTIWHITE')->id,
            'name' => 'optiwhite 6mm',
            'thickness_mm' => '6',
            'purchase_net_price' => '58.50',
        ]);

        $this->assertSame([], $result['errors']);

        /** @var Product $product */
        $product = Product::query()->findOrFail($result['id']);

        $this->assertSame(Section::GLASS, $product->section);
        $this->assertSame(Unit::SQUARE_METER, $product->unit);
        $this->assertSame(6.0, $product->glass?->thickness_mm);
        $this->assertSame('58.50', $product->purchasePriceAt()?->net_price);
        $this->assertSame(PurchasePriceSource::MANUAL, $product->purchasePriceAt()?->source);
    }

    public function test_szklo_bez_grubosci_nie_przechodzi(): void
    {
        $result = $this->service->saveProduct([
            'product_group_id' => $this->group('OPTIWHITE')->id,
            'name' => 'optiwhite bez grubosci',
        ]);

        $this->assertArrayHasKey('thickness_mm', $result['errors']);
        $this->assertNull($result['id']);
    }

    public function test_nazwa_nie_moze_sie_powtorzyc_w_grupie(): void
    {
        $group = $this->group('FLOAT');

        $result = $this->service->saveProduct([
            'product_group_id' => $group->id,
            'name' => 'float 8mm',
            'thickness_mm' => '8',
        ]);

        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function test_ta_sama_nazwa_w_innej_grupie_przechodzi(): void
    {
        $result = $this->service->saveProduct([
            'product_group_id' => $this->group('OPTIWHITE')->id,
            'name' => 'float 8mm',
            'thickness_mm' => '8',
        ]);

        $this->assertSame([], $result['errors']);
    }

    public function test_sekcja_produktu_idzie_z_grupy_a_nie_z_formularza(): void
    {
        $result = $this->service->saveProduct([
            'product_group_id' => $this->group('FLOAT')->id,
            'section' => Section::FITTINGS->value,
            'name' => 'float 4mm ornament',
            'thickness_mm' => '4',
        ]);

        /** @var Product $product */
        $product = Product::query()->findOrFail($result['id']);

        $this->assertSame(Section::GLASS, $product->section);
    }

    public function test_zmiana_ceny_zakupu_zaklada_nowa_wersje(): void
    {
        $product = Product::query()->where('name', 'float 8mm')->firstOrFail();
        $lastWeek = Carbon::today()->subWeek();

        $result = $this->service->saveProduct([
            'product_group_id' => $product->product_group_id,
            'name' => $product->name,
            'thickness_mm' => '8',
            'purchase_net_price' => '60.00',
        ], (int) $product->id, $lastWeek);

        $this->assertSame([], $result['errors']);

        $versions = PurchasePrice::query()
            ->where('product_id', $product->id)
            ->orderBy('valid_from')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame('52.00', $versions[0]->net_price);
        $this->assertSame('60.00', $versions[1]->net_price);
        $this->assertSame('60.00', $product->purchasePriceAt()?->net_price);
    }

    public function test_ta_sama_cena_zakupu_nie_mnozy_wersji(): void
    {
        $product = Product::query()->where('name', 'float 8mm')->firstOrFail();

        $this->service->saveProduct([
            'product_group_id' => $product->product_group_id,
            'name' => $product->name,
            'thickness_mm' => '8',
            'purchase_net_price' => '52.00',
        ], (int) $product->id);

        $this->assertSame(1, PurchasePrice::query()->where('product_id', $product->id)->count());
    }

    public function test_dezaktywowany_produkt_znika_z_macierzy_ale_nie_z_bazy(): void
    {
        $product = Product::query()->where('name', 'float 8mm')->firstOrFail();

        $this->service->saveProduct([
            'product_group_id' => $product->product_group_id,
            'name' => $product->name,
            'thickness_mm' => '8',
            'is_active' => false,
        ], (int) $product->id);

        $matrix = new PriceListService();
        $groupId = (int) $product->product_group_id;

        $visible = array_column($matrix->matrix(Section::GLASS, $groupId)['rows'], 'name');
        $withInactive = array_column(
            $matrix->matrix(Section::GLASS, $groupId, null, true)['rows'],
            'name',
        );

        $this->assertNotContains('float 8mm', $visible);
        $this->assertContains('float 8mm', $withInactive);
        $this->assertNotNull(Product::query()->find($product->id));
    }

    public function test_nowa_grupa_laduje_na_koncu_listy(): void
    {
        $result = $this->service->saveGroup([
            'section' => Section::FITTINGS->value,
            'name' => 'CDA',
            'manufacturer' => 'CDA',
            'series' => 'ETNA',
        ]);

        $this->assertSame([], $result['errors']);

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->findOrFail($result['id']);

        $this->assertSame(Section::FITTINGS, $group->section);
        $this->assertSame('CDA', $group->manufacturer);
        $this->assertGreaterThan(0, $group->position);
    }

    public function test_grupa_o_tej_samej_nazwie_w_sekcji_nie_przechodzi(): void
    {
        $result = $this->service->saveGroup([
            'section' => Section::GLASS->value,
            'name' => 'FLOAT',
        ]);

        $this->assertArrayHasKey('name', $result['errors']);
    }

    public function test_produkt_okuciowy_dostaje_sztuki_i_wykonczenie(): void
    {
        $group = $this->service->saveGroup([
            'section' => Section::FITTINGS->value,
            'name' => 'TERNO',
        ]);

        $result = $this->service->saveProduct([
            'product_group_id' => $group['id'],
            'name' => 'zawias TERNO 2020',
            'finish' => 'chrom',
            'dimension' => '40x30',
        ]);

        $this->assertSame([], $result['errors']);

        /** @var Product $product */
        $product = Product::query()->findOrFail($result['id']);

        $this->assertSame(Unit::PIECE, $product->unit);
        $this->assertSame('chrom', $product->fitting?->finish);
        $this->assertNull($product->glass);
    }
}
