<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductGroup;
use App\Models\PriceSection;
use App\Models\PriceListItem;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Warehouse\StockBoard;
use App\Services\Warehouse\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Sygnał rozjazdu cennika z cenami zakupu.
 *
 * Przyjęcie towaru aktualizuje cenę zakupu, ale cen sprzedaży nie
 * rusza (M-08). Ten sygnał jest jedynym, co o tym mówi — bez niego
 * decyzja „przelicz cennik" nigdy by nie nastąpiła, bo nikt by nie
 * wiedział, że jest co przeliczać.
 */
class PriceDriftTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function przyjecie_po_nowej_cenie_zglasza_rozjazd_ze_stara_i_nowa_cena(): void
    {
        $product = $this->fitting();
        $twoDaysAgo = Carbon::today()->subDays(2);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '40.00',
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => $twoDaysAgo,
        ]);

        $this->priceListItem($product, '3.0000', $twoDaysAgo);

        $service = new PurchaseOrderService();
        $order = $service->draft($this->supplier());
        $item = $service->addItem($order, $product, 10, '50.00');
        $service->send($order);
        $service->receive($order, [['item' => $item, 'quantity' => 10.0]]);

        $board = (new StockBoard())->priceDrift();

        $this->assertSame(1, $board['summary']['drifted']);

        $row = $board['rows'][0];

        $this->assertSame((int) $product->getKey(), $row['product_id']);
        // Obie ceny obok siebie — samo „zmienilo sie" nie daje na czym
        // oprzec decyzji o przeliczeniu.
        $this->assertSame('40.00', $row['previous_purchase_price']);
        $this->assertSame('50.00', $row['purchase_price']);
    }

    #[Test]
    public function przyjecie_nie_rusza_ceny_sprzedazy(): void
    {
        $product = $this->fitting();
        $twoDaysAgo = Carbon::today()->subDays(2);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '40.00',
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => $twoDaysAgo,
        ]);

        $listItem = $this->priceListItem($product, '3.0000', $twoDaysAgo);

        $service = new PurchaseOrderService();
        $order = $service->draft($this->supplier());
        $item = $service->addItem($order, $product, 10, '50.00');
        $service->send($order);
        $service->receive($order, [['item' => $item, 'quantity' => 10.0]]);

        // Oferta wystawiona wczoraj ma zostac wazna. Automat przeliczylby
        // ja na 150 w chwili, gdy przyjechal samochod.
        $this->assertSame('120.00', $listItem->refresh()->effectiveNetPrice());
    }

    #[Test]
    public function cennik_nowszy_od_ceny_zakupu_nie_jest_rozjazdem(): void
    {
        $product = $this->fitting();

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => '40.00',
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => Carbon::today()->subDays(5),
        ]);

        $this->priceListItem($product, '3.0000', Carbon::today());

        $this->assertSame(0, (new StockBoard())->priceDrift()['summary']['drifted']);
    }

    private function priceListItem(Product $product, string $coefficient, Carbon $from): PriceListItem
    {
        /** @var PriceSection $section */
        $section = PriceSection::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'Detaliczny'],
            ['section' => Section::FITTINGS->value, 'name' => 'Detaliczny'],
        );

        /** @var PriceListItem */
        return PriceListItem::query()->create([
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => $coefficient,
            'computed_net_price' => PriceListItem::computePrice('40.00', $coefficient),
            'valid_from' => $from,
        ]);
    }

    private function supplier(): Supplier
    {
        /** @var Supplier */
        return Supplier::query()->firstOrCreate(['name' => 'CDA'], ['name' => 'CDA']);
    }

    private function fitting(): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA', 'position' => 10],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->id,
            'supplier_id' => $this->supplier()->id,
            'section' => Section::FITTINGS->value,
            'code' => 'ROZJAZD-1',
            'name' => 'rotula 33 imbus',
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }
}
