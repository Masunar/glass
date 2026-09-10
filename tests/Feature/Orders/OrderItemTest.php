<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\PriceSection;
use App\Models\ProductGroup;
use App\Models\ProductService;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use App\Services\Orders\OrderItemService;
use Database\Seeders\Core\ProcessSeeder;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Database\Seeders\Core\GlobalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Formatki na zleceniu.
 *
 * Cena pozycji jest snapshotem: liczy się raz, przy zapisie, i zostaje.
 * Testy pilnują trzech rzeczy, na których łatwo stracić pieniądze —
 * że kwota materiału i kwoty procesów nie liczą się podwójnie, że brak
 * ceny nie zamienia się w zero i że zmiana wymiarów przelicza wszystko,
 * a nie tylko materiał.
 */
class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();
        (new ProcessSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();
        (new GlobalParameterSeeder())->run();

        Order::query()->delete();

        $this->service = new OrderItemService();
    }

    private function glass(): Product
    {
        /** @var Product */
        return Product::query()->where('name', 'float 8mm')->firstOrFail();
    }

    private function section(string $name, string $section = Section::GLASS->value): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', $section)
            ->where('name', $name)
            ->firstOrFail();
    }

    /** Cena zakupu 52,00 × 4,0 = 208,00 zł/m². */
    private function priceGlass(): void
    {
        (new PriceListService())->update([[
            'product_id' => $this->glass()->id,
            'price_section_id' => $this->section('Detaliczny podstawowy')->id,
            'coefficient' => '4.0',
        ]]);
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Formatki ' . random_int(1000, 9999),
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

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function pane(array $overrides = []): array
    {
        return [
            'product_id' => $this->glass()->id,
            'width_mm' => 1000,
            'height_mm' => 1000,
            'quantity' => 1,
            'is_tempered' => false,
            ...$overrides,
        ];
    }

    #[Test]
    public function formatka_wycenia_sie_z_cennika_kontrahenta(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        $this->assertSame([], $result['errors']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        // 1 m² × 208,00 zł/m²
        $this->assertSame('208.00', $item->amount);
        $this->assertSame('208.00', $item->unit_net_price);
    }

    #[Test]
    public function sciezka_wyliczenia_zapisuje_sie_razem_z_kwota(): void
    {
        $this->priceGlass();
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $codes = array_column($item->price_path ?? [], 'code');

        // Bez sladu nikt nie odpowie, skad wziela sie ta kwota.
        $this->assertContains('catalogue', $codes);
        $this->assertContains('base', $codes);
    }

    #[Test]
    public function brak_ceny_nie_zamienia_sie_w_zero_tylko_w_powod(): void
    {
        // Cennik pusty — produkt nie ma pozycji w zadnej sekcji.
        $order = $this->order();

        $result = $this->service->savePane((int) $order->getKey(), $this->pane());

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $this->assertSame('0.00', $item->amount);

        $board = $this->service->board((int) $order->getKey());

        $this->assertSame('0.00', $board['totals']['net']);
    }

    #[Test]
    public function proces_bez_pozycji_w_cenniku_jest_oznaczony_a_nie_wyceniony(): void
    {
        $this->priceGlass();
        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        $this->assertCount(1, $item->processes);
        $this->assertSame('0.00', $item->processes[0]->amount);
        // Materiał wyceniony, proces nie — pozycja nie może udawać,
        // że cięcie jest darmowe.
        $this->assertSame('208.00', $item->amount);
    }

    #[Test]
    public function wyceniony_proces_jest_osobnym_wierszem_a_nie_czescia_materialu(): void
    {
        $this->priceGlass();
        $this->priceCutting('12.00');

        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($result['id']);

        // 4 mb obwodu × 12,00 = 48,00; materiał zostaje przy swoich 208,00.
        $this->assertSame('208.00', $item->amount);
        $this->assertSame('48.00', $item->processes[0]->amount);

        $board = $this->service->board((int) $order->getKey());

        $this->assertSame('256.00', $board['totals']['net']);
    }

    #[Test]
    public function zmiana_wymiarow_przelicza_takze_procesy(): void
    {
        $this->priceGlass();
        $this->priceCutting('12.00');

        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $created = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['width_mm' => 2000, 'processes' => [$cutting->id]]),
            $created['id'],
        );

        /** @var OrderItem $item */
        $item = OrderItem::query()->with('processes')->findOrFail($created['id']);

        // 2 m² × 208,00 = 416,00; obwod 6 mb × 12,00 = 72,00
        $this->assertSame('416.00', $item->amount);
        $this->assertSame('72.00', $item->processes[0]->amount);
        $this->assertCount(1, $item->processes);
    }

    #[Test]
    public function usluga_liczy_sie_z_ilosci_i_ceny(): void
    {
        $order = $this->order();

        $result = $this->service->saveService((int) $order->getKey(), [
            'name' => 'montaż luster',
            'quantity' => '6.13',
            'unit_net_price' => '255.00',
        ]);

        $this->assertSame([], $result['errors']);

        /** @var OrderItem $item */
        $item = OrderItem::query()->findOrFail($result['id']);

        $this->assertSame('1563.15', $item->amount);
        $this->assertSame(Section::SERVICES, $item->section);
    }

    #[Test]
    public function wymiar_ponad_mozliwosci_hali_jest_odrzucony(): void
    {
        $order = $this->order();

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['width_mm' => 9000]),
        );

        $this->assertArrayHasKey('width_mm', $result['errors']);
        $this->assertNull($result['id']);
    }

    #[Test]
    public function pozycji_z_cudzego_zlecenia_nie_da_sie_skasowac(): void
    {
        $this->priceGlass();

        $mine = $this->order();
        $other = $this->order();

        $created = $this->service->savePane((int) $other->getKey(), $this->pane());

        $result = $this->service->delete((int) $mine->getKey(), (int) $created['id']);

        $this->assertNotSame([], $result['errors']);
        $this->assertNotNull(OrderItem::query()->find($created['id']));
    }

    #[Test]
    public function usuniecie_pozycji_zabiera_formatke_i_procesy(): void
    {
        $this->priceGlass();
        $this->priceCutting('12.00');

        $order = $this->order();

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $created = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['processes' => [$cutting->id]]),
        );

        $this->service->delete((int) $order->getKey(), (int) $created['id']);

        $this->assertNull(OrderItem::query()->find($created['id']));
        $this->assertSame('0.00', $this->service->board((int) $order->getKey())['totals']['net']);
    }

    #[Test]
    public function odrzucona_alternatywa_nie_wchodzi_do_sumy_ani_do_metrow(): void
    {
        $this->priceGlass();
        $order = $this->order();

        /** @var OrderList $rejected */
        $rejected = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 2,
            'role' => 'alternative',
            'is_included' => false,
        ]);

        $this->service->savePane((int) $order->getKey(), $this->pane());
        $this->service->savePane(
            (int) $order->getKey(),
            $this->pane(['order_list_id' => $rejected->id, 'width_mm' => 2000]),
        );

        $totals = $this->service->board((int) $order->getKey())['totals'];

        $this->assertSame('208.00', $totals['net']);
        $this->assertSame(1.0, $totals['m2']);
    }

    /**
     * Cennik procesu: proces jest w cenniku produktem usługowym.
     * Współczynnik 1,0, żeby cena sprzedaży równała się cenie zakupu
     * i test mówił o wycenie formatki, a nie o marży.
     */
    private function priceCutting(string $purchaseNet): void
    {
        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->create([
            'section' => Section::SERVICES->value,
            'name' => 'Obróbka krawędzi',
            'position' => 10,
        ]);

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::SERVICES->value,
            'name' => 'Cięcie',
            'unit' => Unit::RUNNING_METER->value,
            'vat_rate' => 23,
        ]);

        ProductService::query()->create([
            'product_id' => $product->id,
            'process_id' => $cutting->id,
        ]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $purchaseNet,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => now()->subDay(),
        ]);

        (new PriceListService())->update([[
            'product_id' => $product->id,
            'price_section_id' => $this->section(
                'Detaliczny podstawowy',
                Section::SERVICES->value,
            )->id,
            'coefficient' => '1.0',
        ]]);
    }
}
