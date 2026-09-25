<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\DayOff;
use App\Models\Status;
use App\Models\Location;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderList;
use App\Models\AuditEntry;
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
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderService;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderListService;
use App\Services\Orders\OrderDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Termin klienta wyliczany z pozycji (uwaga klienta z 25.09).
 *
 * Pilnujemy trzech obietnic: termin idzie za pozycjami, ręcznego system
 * nie rusza, a po przekazaniu na produkcję termin stoi. Dzień odniesienia
 * to czwartek 1.10.2026 — pięć dni roboczych to czwartek 8.10.
 */
class OrderDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemService $items;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        DayOff::query()->delete();
        Carbon::setTestNow('2026-10-01 10:00:00');

        $this->items = new OrderItemService();
        $this->position('C', 'Cięcie terminu', 8.0, '0.75');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function position(string $processCode, string $name, ?float $thickness, string $purchaseNet): void
    {
        /** @var Process $process */
        $process = Process::findByCode($processCode);

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::SERVICES->value, 'name' => 'Obróbka krawędzi'],
            ['position' => 10],
        );

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::SERVICES->value,
            'name' => $name,
            'unit' => Unit::RUNNING_METER->value,
            'vat_rate' => 23,
        ]);

        ProductService::query()->create([
            'product_id' => $product->id,
            'process_id' => $process->id,
            'glass_thickness_mm' => $thickness,
        ]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $purchaseNet,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => now()->subDay(),
        ]);

        /** @var PriceSection $section */
        $section = PriceSection::query()
            ->where('section', Section::SERVICES->value)
            ->where('name', 'Detaliczny podstawowy')
            ->firstOrFail();

        (new PriceListService())->update([[
            'product_id' => $product->id,
            'price_section_id' => $section->id,
            'coefficient' => '1.0',
        ]]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function order(string $status = 'DO_WYCENY', array $attributes = []): Order
    {
        /** @var Status $state */
        $state = Status::findByCode(StatusDomain::ORDER, $status);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Termin ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $state->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            ...$attributes,
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
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    private function pane(Order $order, int $days): array
    {
        /** @var Process $cutting */
        $cutting = Process::findByCode('C');
        /** @var Product $glass */
        $glass = Product::query()->where('name', 'float 8mm')->firstOrFail();

        return $this->items->savePane((int) $order->getKey(), [
            'product_id' => $glass->id,
            'width_mm' => 1000,
            'height_mm' => 1000,
            'quantity' => 1,
            'processes' => [['process_id' => $cutting->id, 'days' => $days]],
        ]);
    }

    private function fresh(Order $order): Order
    {
        /** @var Order */
        return Order::query()->findOrFail($order->getKey());
    }

    #[Test]
    public function termin_wylicza_sie_z_pozycji_w_dniach_roboczych(): void
    {
        $order = $this->order();

        $this->assertSame([], $this->pane($order, 5)['errors']);

        $fresh = $this->fresh($order);
        $this->assertSame('2026-10-08', $fresh->client_deadline?->toDateString());
        $this->assertSame(5, $fresh->deadline_days);
        $this->assertFalse($fresh->deadline_manual);
        $this->assertTrue(
            AuditEntry::query()->where('auditable_id', $order->getKey())->where('event', 'deadline_computed')->exists(),
        );
    }

    #[Test]
    public function dzien_wolny_zakladu_odsuwa_termin(): void
    {
        DayOff::query()->create(['date' => '2026-10-05', 'name' => 'Inwentaryzacja', 'is_active' => true]);
        $order = $this->order();

        $this->pane($order, 5);

        $this->assertSame('2026-10-09', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function ta_sama_liczba_dni_nie_przesuwa_daty(): void
    {
        $order = $this->order();
        $this->pane($order, 5);

        // Tydzien pozniej krotsza formatka: najdluzsza dalej ma 5 dni.
        Carbon::setTestNow('2026-10-08 10:00:00');
        $this->pane($order, 2);

        $this->assertSame('2026-10-08', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function dluzsza_formatka_liczy_od_dnia_zmiany(): void
    {
        $order = $this->order();
        $this->pane($order, 5);

        Carbon::setTestNow('2026-10-02 10:00:00');
        $this->pane($order, 7);

        // Piatek 2.10 + 7 dni roboczych: 5, 6, 7, 8, 9, 12, 13.
        $this->assertSame('2026-10-13', $this->fresh($order)->client_deadline?->toDateString());
        $this->assertSame(7, $this->fresh($order)->deadline_days);
    }

    #[Test]
    public function reczny_termin_zostaje(): void
    {
        $order = $this->order(attributes: ['client_deadline' => '2026-12-01', 'deadline_manual' => true]);

        $this->pane($order, 5);

        $this->assertSame('2026-12-01', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function po_przekazaniu_na_produkcje_termin_stoi(): void
    {
        $order = $this->order('PRODUKCJA');

        $this->pane($order, 5);

        $this->assertNull($this->fresh($order)->client_deadline);
    }

    #[Test]
    public function usuniecie_pozycji_i_wylaczenie_listy_przeliczaja_termin(): void
    {
        $order = $this->order();
        $this->pane($order, 5);
        $long = $this->pane($order, 7);

        $this->assertSame('2026-10-12', $this->fresh($order)->client_deadline?->toDateString());

        $this->items->delete((int) $order->getKey(), (int) $long['id']);
        $this->assertSame(5, $this->fresh($order)->deadline_days);
        $this->assertSame('2026-10-08', $this->fresh($order)->client_deadline?->toDateString());

        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();
        (new OrderListService())->save((int) $order->getKey(), ['is_included' => false], (int) $list->getKey());

        // Nic nie jest wliczone — nie ma z czego liczyc terminu.
        $this->assertNull($this->fresh($order)->client_deadline);
        $this->assertNull($this->fresh($order)->deadline_days);
    }

    #[Test]
    public function termin_z_karty_jest_reczny_a_wyczyszczony_wraca_do_automatu(): void
    {
        $order = $this->order();
        $this->pane($order, 5);
        $details = new OrderDetailsService();

        $details->deadline((int) $order->getKey(), ['client_deadline' => '2026-11-30']);

        $this->assertTrue($this->fresh($order)->deadline_manual);

        $this->pane($order, 9);
        $this->assertSame('2026-11-30', $this->fresh($order)->client_deadline?->toDateString());

        $details->deadline((int) $order->getKey(), ['client_deadline' => null]);

        $fresh = $this->fresh($order);
        $this->assertFalse($fresh->deadline_manual);
        // Najdluzsza formatka ma 9 dni: od czwartku 1.10 to sroda 14.10.
        $this->assertSame('2026-10-14', $fresh->client_deadline?->toDateString());
    }

    #[Test]
    public function termin_przy_zakladaniu_zlecenia_jest_reczny(): void
    {
        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Termin z oferty',
            'tax_id' => '8522347066',
        ]);
        /** @var Location $pickup */
        $pickup = Location::query()->firstOrFail();

        $service = new OrderService();
        $input = [
            'contractor_id' => $contractor->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'pickup_location_id' => $pickup->id,
        ];

        $withDate = $service->create([...$input, 'client_deadline' => '2026-10-20']);
        $withoutDate = $service->create($input);

        $this->assertSame([], $withDate['errors']);
        $this->assertTrue(Order::query()->findOrFail((int) $withDate['id'])->deadline_manual);
        $this->assertFalse(Order::query()->findOrFail((int) $withoutDate['id'])->deadline_manual);
    }
}
