<?php

declare(strict_types=1);

namespace Tests\Feature\Pricing;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\PriceSection;
use App\Models\PriceListItem;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Warehouse\StockBoard;
use App\Services\Pricing\PurchasePriceLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Przeliczenie cennika po zmianie ceny zakupu.
 *
 * Przyjęcie towaru aktualizuje cenę zakupu i **nie rusza cen
 * sprzedaży** (M-08). Ten ekran jest drugą połową tej decyzji: pokazuje
 * rozjazd i pozwala go domknąć świadomie.
 */
class PriceRecalculationTest extends TestCase
{
    use RefreshDatabase;

    private PriceListService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PriceListService();
    }

    #[Test]
    public function przeliczenie_bierze_nowa_cene_zakupu(): void
    {
        $product = $this->fitting('PRZELICZ-1');
        $this->purchase($product, '40.00', Carbon::today()->subDays(5));
        $this->listItem($product, '3.0000', Carbon::today()->subDays(5), '120.00');

        // Dostawa po nowej cenie — cennik jeszcze o niej nie wie.
        (new PurchasePriceLedger())->set(
            $product,
            '50.00',
            Carbon::today(),
            PurchasePriceSource::DELIVERY,
        );

        $result = $this->service->recalculate([(int) $product->getKey()]);

        $this->assertSame(1, $result['recalculated']);
        $this->assertSame('150.00', $this->currentPrice($product));
    }

    #[Test]
    public function po_przeliczeniu_rozjazd_znika(): void
    {
        $product = $this->fitting('PRZELICZ-2');
        $this->purchase($product, '40.00', Carbon::today()->subDays(5));
        $this->listItem($product, '3.0000', Carbon::today()->subDays(5), '120.00');

        (new PurchasePriceLedger())->set(
            $product,
            '50.00',
            Carbon::today(),
            PurchasePriceSource::DELIVERY,
        );

        $board = new StockBoard();

        $this->assertSame(1, $board->priceDrift()['summary']['drifted']);

        $this->service->recalculate([(int) $product->getKey()]);

        $this->assertSame(0, $board->priceDrift()['summary']['drifted']);
    }

    #[Test]
    public function cena_reczna_zostaje_reczna(): void
    {
        // Zmienia sie wylacznie to, co wynikalo ze wspolczynnika.
        $product = $this->fitting('PRZELICZ-3');
        $this->purchase($product, '40.00', Carbon::today()->subDays(5));
        $item = $this->listItem($product, '3.0000', Carbon::today()->subDays(5), '120.00');
        $item->manual_net_price = '99.00';
        $item->save();

        (new PurchasePriceLedger())->set(
            $product,
            '50.00',
            Carbon::today(),
            PurchasePriceSource::DELIVERY,
        );

        $this->service->recalculate([(int) $product->getKey()]);

        $current = $this->currentItem($product);

        $this->assertNotNull($current);
        $this->assertSame('99.00', $current->manual_net_price);
        // Wyliczona i tak sie odswieza — sluzy do porownania marzy.
        $this->assertSame('150.00', $current->computed_net_price);
        $this->assertSame('99.00', $current->effectiveNetPrice());
    }

    #[Test]
    public function stara_wersja_cennika_zostaje_zamknieta_a_nie_skasowana(): void
    {
        // Oferta sprzed tygodnia musi dac sie odtworzyc.
        $product = $this->fitting('PRZELICZ-4');
        $week = Carbon::today()->subDays(7);

        $this->purchase($product, '40.00', $week);
        $this->listItem($product, '3.0000', $week, '120.00');

        (new PurchasePriceLedger())->set(
            $product,
            '50.00',
            Carbon::today(),
            PurchasePriceSource::DELIVERY,
        );

        $this->service->recalculate([(int) $product->getKey()]);

        $this->assertSame(2, PriceListItem::query()->where('product_id', $product->id)->count());

        /** @var PriceListItem|null $old */
        $old = PriceListItem::query()
            ->where('product_id', $product->id)
            ->whereNotNull('valid_to')
            ->first();

        $this->assertNotNull($old);
        $this->assertSame('120.00', $old->computed_net_price);
    }

    #[Test]
    public function pozycja_bez_wspolczynnika_jest_pomijana(): void
    {
        // Jej cena nie zalezy od ceny zakupu, wiec nie ma z czego liczyc.
        $product = $this->fitting('PRZELICZ-5');
        $this->purchase($product, '40.00', Carbon::today()->subDays(5));
        $this->listItem($product, '0.0000', Carbon::today()->subDays(5), null, '80.00');

        $result = $this->service->recalculate([(int) $product->getKey()]);

        $this->assertSame(0, $result['recalculated']);
        $this->assertSame(1, $result['skipped']);
    }

    #[Test]
    public function pusta_lista_nie_rusza_niczego(): void
    {
        $this->assertSame(
            ['recalculated' => 0, 'skipped' => 0],
            $this->service->recalculate([]),
        );
    }

    private function currentPrice(Product $product): ?string
    {
        return $this->currentItem($product)?->effectiveNetPrice();
    }

    private function currentItem(Product $product): ?PriceListItem
    {
        /** @var PriceListItem|null */
        return PriceListItem::query()
            ->where('product_id', $product->id)
            ->whereNull('valid_to')
            ->orderByDesc('valid_from')
            ->first();
    }

    private function purchase(Product $product, string $price, Carbon $from): void
    {
        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $price,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => $from,
        ]);
    }

    private function listItem(
        Product $product,
        string $coefficient,
        Carbon $from,
        ?string $computed,
        ?string $manual = null,
    ): PriceListItem {
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
            'computed_net_price' => $computed,
            'manual_net_price' => $manual,
            'valid_from' => $from,
        ]);
    }

    private function fitting(string $code): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA', 'position' => 10],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::FITTINGS->value,
            'code' => $code,
            'name' => 'Okucie ' . $code,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }
}
