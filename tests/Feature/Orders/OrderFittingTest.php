<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\FittingSet;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\PriceSection;
use App\Models\ProductGroup;
use App\Models\PurchasePrice;
use App\Models\FittingSetItem;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Orders\OrderFittingService;

/**
 * Okucia na zleceniu.
 *
 * Najważniejsza różnica wobec usług: **cenę okucia liczy cennik, nie
 * handlowiec**. Okucie jest pozycją katalogową, więc idzie tą samą
 * drogą co szkło i zostawia ścieżkę wyliczenia.
 */
class OrderFittingTest extends TestCase
{
    use RefreshDatabase;

    private OrderFittingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderFittingService();
    }

    private function fitting(string $name, string $purchaseNet): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA', 'position' => 10],
        );

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::FITTINGS->value,
            'name' => $name,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $purchaseNet,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => now()->subYear(),
        ]);

        /** @var PriceSection $section */
        $section = PriceSection::query()
            ->where('section', Section::FITTINGS->value)
            ->where('is_default', true)
            ->firstOrFail();

        (new PriceListService())->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => '2.0',
        ]]);

        return $product;
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Okucia ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]);

        OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
        ]);

        return $order;
    }

    #[Test]
    public function cene_okucia_bierze_cennik_a_nie_formularz(): void
    {
        $product = $this->fitting('rotula 33 imbus', '50.52');
        $order = $this->order();

        $result = $this->service->save((int) $order->getKey(), [
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->assertSame([], $result['errors']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        // 50,52 zakupu × 2,0 = 101,04 za sztukę, trzy sztuki = 303,12.
        $this->assertSame('101.04', $item->unit_net_price);
        $this->assertSame('303.12', $item->amount);
        $this->assertNotSame([], $item->price_path);
    }

    #[Test]
    public function wpisana_cena_nadpisuje_cennik(): void
    {
        $product = $this->fitting('gałka 35 x 35', '36.04');
        $order = $this->order();

        $result = $this->service->save((int) $order->getKey(), [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_net_price' => '60.00',
        ]);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        // Sciezka wyliczenia zostaje pusta: cena nie wyszla z cennika,
        // wiec nie ma czego pokazywac jako jej pochodzenie.
        $this->assertSame('60.00', $item->unit_net_price);
        $this->assertSame('120.00', $item->amount);
        $this->assertSame([], $item->price_path);
    }

    #[Test]
    public function okucie_bez_ceny_w_cenniku_nie_kosztuje_zera(): void
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA', 'position' => 10],
        );

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::FITTINGS->value,
            'name' => 'uchwyt bez ceny',
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);

        $order = $this->order();

        $result = $this->service->save((int) $order->getKey(), [
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        // Brak ceny zapisuje sie jako brak. Zero znaczyloby „okucie za
        // darmo" i nie dalo sie go odroznic od ceny nieznanej.
        $this->assertNull($item->unit_net_price);
        $this->assertSame('0.00', $item->amount);
    }

    #[Test]
    public function zero_jest_prawidlowa_cena_okucia(): void
    {
        $product = $this->fitting('zaslepka', '2.00');
        $order = $this->order();

        // Okucie dorzucone gratis to decyzja handlowa, nie brak danych.
        $result = $this->service->save((int) $order->getKey(), [
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_net_price' => '0',
        ]);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $this->assertSame('0.00', $item->unit_net_price);
    }

    #[Test]
    public function zestaw_rozwija_sie_na_zwykle_pozycje(): void
    {
        $first = $this->fitting('FIXS złoty 2,3m', '41.48');
        $second = $this->fitting('GAŁKA DO DRZWI 35 x 35', '36.04');
        $order = $this->order();

        /** @var FittingSet $set */
        $set = FittingSet::query()->create([
            'name' => 'System przesuwny terno clear',
            'kind' => FittingSet::KIND_TEMPLATE,
        ]);

        FittingSetItem::query()->create([
            'fitting_set_id' => $set->id,
            'product_id' => $first->id,
            'quantity' => 1,
            'position' => 10,
        ]);
        FittingSetItem::query()->create([
            'fitting_set_id' => $set->id,
            'product_id' => $second->id,
            'quantity' => 2,
            'position' => 20,
        ]);

        $result = $this->service->addSet((int) $order->getKey(), ['fitting_set_id' => $set->id]);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['ids']);

        // Zestaw jest szablonem, nie bytem na zleceniu: po dolozeniu
        // zostaja zwykle pozycje, kazda do skasowania i zmiany osobno.
        $items = OrderItem::query()
            ->whereIn('id', $result['ids'])
            ->orderBy('position')
            ->get();

        $this->assertSame('82.96', $items[0]->unit_net_price);
        $this->assertSame('82.96', $items[0]->amount);
        $this->assertSame('144.16', $items[1]->amount);
    }

    #[Test]
    public function zapisana_konfiguracja_nie_jest_biblioteka_szablonow(): void
    {
        $this->fitting('cokolwiek', '10.00');

        FittingSet::query()->create([
            'name' => '23) Marta Grabowska',
            'kind' => FittingSet::KIND_CONFIGURATION,
        ]);
        FittingSet::query()->create([
            'name' => 'Kabina prysznicowa standard',
            'kind' => FittingSet::KIND_TEMPLATE,
        ]);

        $names = array_column($this->service->sets(), 'name');

        // Komplet zlozony dla jednego klienta ma trafic do jego historii,
        // a nie do biblioteki. Tak wlasnie stara biblioteka zapelnila sie
        // wpisami „test", „140" i „Marta Grabowska" — dwa razy.
        $this->assertSame(['Kabina prysznicowa standard'], $names);
    }

    #[Test]
    public function okucia_stoja_w_osobnej_sekcji_tablicy(): void
    {
        $product = $this->fitting('rotula', '50.52');
        $order = $this->order();

        $this->service->save((int) $order->getKey(), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $board = (new \App\Services\Orders\OrderItemService())->board((int) $order->getKey());

        $this->assertCount(1, $board['lists'][0]['fittings']);
        $this->assertCount(0, $board['lists'][0]['services']);
        $this->assertCount(0, $board['lists'][0]['glass']);
    }
}
