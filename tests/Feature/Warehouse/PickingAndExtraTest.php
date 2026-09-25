<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\StockMovement;
use App\Models\ExtraDelivery;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\ProductGroup;
use App\Models\Supplier;
use App\Enum\StockMovementType;
use App\Enum\ExtraDeliveryStatus;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Warehouse\OrderStock;
use App\Services\Warehouse\PickingList;
use App\Services\Warehouse\StockLedger;
use App\Services\Warehouse\WarehouseDocuments;
use App\Services\Warehouse\ExtraDeliveryService;
use App\Services\Warehouse\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Kompletacja okuć, dostawy dodatkowe i wydruk zamówienia (uwagi
 * klienta z 25.09, punkty 3b–3d).
 */
class PickingAndExtraTest extends TestCase
{
    use RefreshDatabase;

    private StockLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        $this->ledger = new StockLedger();
    }

    private function fitting(string $name): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::FITTINGS->value, 'name' => 'CDA - ETNA'],
            ['position' => 10],
        );

        /** @var Product */
        return Product::query()->create([
            'product_group_id' => $group->id,
            'section' => Section::FITTINGS->value,
            'name' => $name,
            'unit' => Unit::PIECE->value,
            'vat_rate' => 23,
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function order(string $status = 'ZLECENIE', array $attributes = []): Order
    {
        /** @var Status $state */
        $state = Status::findByCode(StatusDomain::ORDER, $status);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Kompletacja ' . random_int(1000, 9999),
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

    private function addFitting(Order $order, Product $product, float $quantity): OrderItem
    {
        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();

        /** @var OrderItem */
        return OrderItem::query()->create([
            'order_list_id' => $list->id,
            'product_id' => $product->id,
            'section' => Section::FITTINGS->value,
            'name' => $product->name,
            'quantity' => number_format($quantity, 2, '.', ''),
            'unit_net_price' => '100.00',
            'amount' => number_format($quantity * 100, 2, '.', ''),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function board(string $from, string $to, string $today = '2026-10-05'): array
    {
        return (new PickingList())->board(Carbon::parse($from), Carbon::parse($to), Carbon::parse($today));
    }

    /**
     * @param array<string, mixed> $board
     * @return list<int>
     */
    private function numbers(array $board): array
    {
        /** @var list<array{number: int}> $rows */
        $rows = $board['rows'];

        return array_map(static fn(array $row): int => $row['number'], $rows);
    }

    #[Test]
    public function lista_bierze_zlecenia_z_okuciami_na_termin(): void
    {
        $handle = $this->fitting('klamka');
        $this->ledger->receive($handle, 10);

        $inRange = $this->order(attributes: ['client_deadline' => '2026-10-07']);
        $shifted = $this->order(attributes: ['client_deadline' => '2026-10-20', 'shifted_deadline' => '2026-10-06']);
        $later = $this->order(attributes: ['client_deadline' => '2026-10-20']);
        $quote = $this->order('DO_WYCENY', ['client_deadline' => '2026-10-07']);
        $noFittings = $this->order(attributes: ['client_deadline' => '2026-10-07']);

        foreach ([$inRange, $shifted, $later, $quote] as $order) {
            $this->addFitting($order, $handle, 2);
        }

        $numbers = $this->numbers($this->board('2026-10-05', '2026-10-07'));

        // Przesuniety termin wygrywa z terminem klienta; ostatni dzien
        // zakresu tez sie liczy.
        $this->assertSame([$shifted->number, $inRange->number], $numbers);
        $this->assertNotContains($later->number, $numbers);
        $this->assertNotContains($quote->number, $numbers);
        $this->assertNotContains($noFittings->number, $numbers);
    }

    #[Test]
    public function zalegle_zostaja_dopoki_nikt_ich_nie_przygotuje(): void
    {
        $handle = $this->fitting('klamka');
        $late = $this->order(attributes: ['client_deadline' => '2026-10-01']);
        $undated = $this->order();
        $this->addFitting($late, $handle, 1);
        $this->addFitting($undated, $handle, 1);

        $board = $this->board('2026-10-05', '2026-10-07');
        $this->assertSame([$late->number, $undated->number], $this->numbers($board));

        /** @var list<array<string, mixed>> $rows */
        $rows = $board['rows'];
        $this->assertTrue($rows[0]['is_late']);

        (new PickingList())->mark((int) $late->getKey(), true);

        $this->assertSame([$undated->number], $this->numbers($this->board('2026-10-05', '2026-10-07')));
        $this->assertSame(1, AuditEntry::query()->where('event', 'fittings_prepared')->count());
    }

    #[Test]
    public function stan_okucia_jak_w_kolumnie_okuc(): void
    {
        $handle = $this->fitting('klamka');
        $hinge = $this->fitting('zawias');
        $this->ledger->receive($handle, 5);
        $this->ledger->receive($hinge, 1);

        $order = $this->order(attributes: ['client_deadline' => '2026-10-06']);
        $this->addFitting($order, $handle, 2);
        $this->addFitting($order, $hinge, 3);

        /** @var list<array{missing: int, fittings: list<array{name: string, state: string}>}> $rows */
        $rows = $this->board('2026-10-05', '2026-10-07')['rows'];
        $states = array_column($rows[0]['fittings'], 'state', 'name');

        $this->assertSame(['klamka' => 'in_stock', 'zawias' => 'short'], $states);
        $this->assertSame(1, $rows[0]['missing']);

        // Po wydaniu na produkcje wszystko jest „wydane".
        $this->ledger->receive($hinge, 2);
        (new OrderStock())->issue($order);

        /** @var list<array{fittings: list<array{name: string, state: string}>}> $after */
        $after = $this->board('2026-10-05', '2026-10-07')['rows'];
        $this->assertSame(['issued', 'issued'], array_column($after[0]['fittings'], 'state'));
    }

    #[Test]
    public function zmiana_okuc_kasuje_przygotowane(): void
    {
        $handle = $this->fitting('klamka');
        $order = $this->order(attributes: ['client_deadline' => '2026-10-06']);
        $item = $this->addFitting($order, $handle, 2);
        (new PickingList())->mark((int) $order->getKey(), true);

        $item->quantity = '3.00';
        $item->save();

        $this->assertNull(Order::query()->findOrFail((int) $order->getKey())->fittings_prepared_at);
    }

    #[Test]
    public function dostawa_dodatkowa_przed_produkcja_zostaje_na_stanie(): void
    {
        $handle = $this->fitting('klamka');
        $order = $this->order(attributes: ['client_deadline' => '2026-10-06']);
        $this->addFitting($order, $handle, 2);
        $service = new ExtraDeliveryService();

        $created = $service->create([
            'order_id' => $order->getKey(),
            'reason' => 'reorder',
            'items' => [['product_id' => $handle->id, 'quantity' => 2]],
        ]);
        $this->assertSame([], $created['errors']);

        $this->assertSame([], $service->receive((int) $created['id'])['errors']);

        $this->assertSame('2.000', (string) $this->ledger->level($handle)->quantity);
        $this->assertTrue(StockMovement::query()
            ->where('order_id', $order->getKey())
            ->where('type', StockMovementType::RECEIPT->value)
            ->where('document', 'like', 'DD/%')
            ->exists());
        $this->assertFalse(StockMovement::query()
            ->where('order_id', $order->getKey())
            ->where('type', StockMovementType::ISSUE->value)
            ->exists());

        /** @var ExtraDelivery $delivery */
        $delivery = ExtraDelivery::query()->findOrFail((int) $created['id']);
        $this->assertSame(ExtraDeliveryStatus::RECEIVED, $delivery->status);

        // Drugie przyjecie tej samej dostawy nie przechodzi.
        $this->assertArrayHasKey('status', $service->receive((int) $created['id'])['errors']);
    }

    #[Test]
    public function dostawa_dodatkowa_po_wydaniu_od_razu_idzie_na_zlecenie(): void
    {
        $handle = $this->fitting('klamka');
        $this->ledger->receive($handle, 2);
        $order = $this->order('PRODUKCJA', ['client_deadline' => '2026-10-06']);
        $this->addFitting($order, $handle, 2);
        (new OrderStock())->issue($order);

        $service = new ExtraDeliveryService();
        $created = $service->create([
            'order_id' => $order->getKey(),
            'reason' => 'wrong_item',
            'items' => [['product_id' => $handle->id, 'quantity' => 1]],
        ]);
        $service->receive((int) $created['id']);

        // Przyjeta i od razu wydana: stan bez zmian, dwa ruchy z numerem.
        $this->assertSame('0.000', (string) $this->ledger->level($handle)->quantity);
        // Przyjecie niesie numer dostawy jako dokument, wydanie (RW) w uwadze.
        $this->assertSame(2, StockMovement::query()
            ->where('order_id', $order->getKey())
            ->where(static fn($query) => $query->where('document', 'like', 'DD/%')->orWhere('note', 'like', 'DD/%'))
            ->count());

        /** @var list<array{extra: list<array{number: int}>}> $rows */
        $rows = $this->board('2026-10-05', '2026-10-07')['rows'];
        $this->assertCount(1, $rows[0]['extra']);
    }

    #[Test]
    public function dostawa_dodatkowa_dotyczy_tylko_okuc(): void
    {
        $order = $this->order();
        /** @var Product $glass */
        $glass = Product::query()->where('section', Section::GLASS->value)->firstOrFail();

        $result = (new ExtraDeliveryService())->create([
            'order_id' => $order->getKey(),
            'reason' => 'claim',
            'items' => [['product_id' => $glass->id, 'quantity' => 1]],
        ]);

        $this->assertArrayHasKey('items', $result['errors']);
        $this->assertSame(0, ExtraDelivery::query()->count());
    }

    #[Test]
    public function anulowana_dostawa_nie_rusza_stanu(): void
    {
        $handle = $this->fitting('klamka');
        $order = $this->order();
        $service = new ExtraDeliveryService();
        $created = $service->create([
            'order_id' => $order->getKey(),
            'reason' => 'claim',
            'items' => [['product_id' => $handle->id, 'quantity' => 1]],
        ]);

        $this->assertSame([], $service->cancel((int) $created['id'])['errors']);
        $this->assertArrayHasKey('status', $service->receive((int) $created['id'])['errors']);
        $this->assertSame('0.000', (string) $this->ledger->level($handle)->quantity);
    }

    #[Test]
    public function wydruk_zamowienia_i_kompletacji_to_pdf(): void
    {
        $handle = $this->fitting('klamka');
        /** @var Supplier $supplier */
        $supplier = Supplier::query()->create(['name' => 'Dostawca wydruku', 'is_active' => true]);
        $purchase = (new PurchaseOrderService())->draft($supplier);
        (new PurchaseOrderService())->addItem($purchase, $handle, 4);

        $documents = new WarehouseDocuments();
        $pdf = $documents->purchaseOrder($purchase);

        $this->assertStringStartsWith('%PDF', $pdf);

        $order = $this->order(attributes: ['client_deadline' => '2026-10-06']);
        $this->addFitting($order, $handle, 2);

        $this->assertStringStartsWith('%PDF', $documents->picking(
            Carbon::parse('2026-10-05'),
            Carbon::parse('2026-10-07'),
            Carbon::parse('2026-10-05'),
        ));
    }
}
