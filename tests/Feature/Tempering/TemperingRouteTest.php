<?php

declare(strict_types=1);

namespace Tests\Feature\Tempering;

use Tests\TestCase;
use App\Enum\Unit;
use Carbon\Carbon;
use RuntimeException;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Product;
use App\Models\Vehicle;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\OrderPane;
use App\Models\Supplier;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\ProductGlass;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\ProductGroup;
use App\Models\TemperingItem;
use App\Models\TemperingBatch;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Tempering\TemperingBoard;
use App\Services\Tempering\TemperingQueue;
use App\Services\Tempering\TemperingBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Partia jako kurs samochodu.
 *
 * Ładowność **ostrzega, nie blokuje**: człowiek przy aucie wie więcej
 * niż tabela — może dorzucić drugi kurs albo zdjąć jedną szybę na
 * miejscu. Skład zmienia się do wyjazdu, bo partia jest planem.
 */
class TemperingRouteTest extends TestCase
{
    use RefreshDatabase;

    private TemperingBatchService $service;
    private TemperingQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TemperingBatchService();
        $this->queue = new TemperingQueue();
    }

    #[Test]
    public function partia_bez_auta_nie_liczy_zapelnienia(): void
    {
        // Bez auta nie ma wobec czego wazyc — sto procent z niczego
        // byloby liczba wymyslona.
        $batch = $this->batchWith(quantity: 2);

        $card = (new TemperingBoard())->card($batch);

        $this->assertNull($card['payload_kg']);
        $this->assertNull($card['load_percent']);
        $this->assertNull($card['over_by_kg']);
    }

    #[Test]
    public function zapelnienie_liczy_sie_wobec_ladownosci_auta(): void
    {
        // 1,5 x 1,0 m, 2 szt., 8 mm = 3 m2 x 20 kg/m2 = 60 kg.
        $batch = $this->batchWith(quantity: 2);
        $this->service->plan($batch, $this->vehicle(600), null, null);

        $card = (new TemperingBoard())->card($batch->refresh());

        $this->assertSame(60.0, $card['load_kg']);
        $this->assertSame(600, $card['payload_kg']);
        $this->assertSame(10, $card['load_percent']);
        $this->assertNull($card['over_by_kg']);
    }

    #[Test]
    public function przekroczenie_ladownosci_ostrzega_ale_nie_blokuje(): void
    {
        $batch = $this->batchWith(quantity: 2);
        $this->service->plan($batch, $this->vehicle(50), null, null);

        $card = (new TemperingBoard())->card($batch->refresh());

        $this->assertSame(10.0, $card['over_by_kg']);
        $this->assertSame(120, $card['load_percent']);

        // I mimo to da sie wyslac: decyzja nalezy do czlowieka przy aucie.
        $sent = $this->service->send($batch);

        $this->assertSame('sent', $sent->status->value);
    }

    #[Test]
    public function sklad_partii_zmienia_sie_do_wyjazdu(): void
    {
        $order = $this->order();
        $this->addPane($order, 2);
        $this->addPane($order, 3, 'druga szyba');
        $this->queue->sync($order);

        /** @var list<int> $ids */
        $ids = TemperingItem::query()->pluck('id')->all();

        $batch = $this->service->draft($this->supplier());
        $this->service->add($batch, [$ids[0]]);

        $this->assertSame(1, $batch->items()->count());

        // Dolozenie i zdjecie przed wyjazdem — partia jest planem.
        $this->service->add($batch, [$ids[1]]);
        $this->assertSame(2, $batch->refresh()->items()->count());

        /** @var TemperingItem $first */
        $first = TemperingItem::query()->findOrFail($ids[0]);
        $this->service->remove($batch, $first);

        $this->assertSame(1, $batch->refresh()->items()->count());
    }

    #[Test]
    public function po_wyjezdzie_skladu_sie_nie_zmienia(): void
    {
        $batch = $this->batchWith(quantity: 2);
        $this->service->send($batch);

        $this->expectException(RuntimeException::class);
        $this->service->add($batch, [1]);
    }

    #[Test]
    public function wyslanie_bierze_planowana_date_wyjazdu(): void
    {
        // Wyjazd zgodny z planem to najczestszy przypadek.
        $batch = $this->batchWith(quantity: 2);
        $planned = Carbon::today()->addDays(3);

        $this->service->plan($batch, $this->vehicle(600), $planned, null);
        $this->service->send($batch);

        $this->assertSame(
            $planned->toDateString(),
            $batch->refresh()->sent_at?->toDateString(),
        );
    }

    #[Test]
    public function faktyczny_wyjazd_moze_sie_roznic_od_planu(): void
    {
        // Roznica miedzy planem a faktem to informacja, wiec obie daty
        // zostaja zapisane osobno.
        $batch = $this->batchWith(quantity: 2);
        $planned = Carbon::today()->subDays(2);
        $actual = Carbon::today();

        $this->service->plan($batch, null, $planned, null);
        $this->service->send($batch, $actual);

        $fresh = $batch->refresh();

        $this->assertSame($planned->toDateString(), $fresh->departure_at?->toDateString());
        $this->assertSame($actual->toDateString(), $fresh->sent_at?->toDateString());
    }

    #[Test]
    public function planu_nie_zmienia_sie_po_wyjezdzie(): void
    {
        $batch = $this->batchWith(quantity: 2);
        $this->service->send($batch);

        $this->expectException(RuntimeException::class);
        $this->service->plan($batch, $this->vehicle(600), Carbon::today(), null);
    }

    #[Test]
    public function kolejka_stawia_pilne_przed_terminem(): void
    {
        // Ta sama regula, co w kolejce hali: po to sie pilne zaznacza.
        $late = $this->order();
        $lateItem = $this->addPane($late, 1);
        $late->client_deadline = Carbon::today()->addDay();
        $late->save();

        $urgent = $this->order();
        $urgentItem = $this->addPane($urgent, 1);
        $urgentItem->is_urgent = true;
        $urgentItem->save();
        $urgent->client_deadline = Carbon::today()->addMonth();
        $urgent->save();

        $this->queue->sync($late);
        $this->queue->sync($urgent);

        $board = (new TemperingBoard())->queue();

        $this->assertTrue($board['rows'][0]['is_urgent']);
        $this->assertSame((int) $urgent->getKey(), $board['rows'][0]['order_id']);
        $this->assertSame((int) $lateItem->list?->order_id, $board['rows'][1]['order_id']);
    }

    private function batchWith(float $quantity): TemperingBatch
    {
        $order = $this->order();
        $this->addPane($order, $quantity);
        $this->queue->sync($order);

        /** @var TemperingItem $position */
        $position = TemperingItem::query()->orderByDesc('id')->firstOrFail();

        $batch = $this->service->draft($this->supplier());
        $this->service->add($batch, [(int) $position->getKey()]);

        return $batch->refresh();
    }

    private function vehicle(int $payload): Vehicle
    {
        /** @var Vehicle */
        return Vehicle::query()->create([
            'name' => 'Auto ' . $payload,
            'payload_kg' => $payload,
        ]);
    }

    private function supplier(): Supplier
    {
        /** @var Supplier */
        return Supplier::query()->firstOrCreate(
            ['name' => 'Hartownia Stobno'],
            ['name' => 'Hartownia Stobno'],
        );
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Klient ' . random_int(1000, 9999),
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

        return $order->fresh() ?? $order;
    }

    private function addPane(Order $order, float $quantity, string $name = 'float 8mm'): OrderItem
    {
        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();

        $product = $this->glass($name);

        /** @var OrderItem $item */
        $item = OrderItem::query()->create([
            'order_list_id' => $list->id,
            'product_id' => $product->id,
            'section' => Section::GLASS->value,
            'name' => $product->name,
            'quantity' => number_format($quantity, 3, '.', ''),
            'unit_net_price' => '100.00',
            'amount' => number_format($quantity * 100, 2, '.', ''),
        ]);

        OrderPane::query()->create([
            'order_item_id' => $item->id,
            'width_mm' => 1500,
            'height_mm' => 1000,
            'is_tempered' => true,
        ]);

        return $item;
    }

    private function glass(string $name): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::GLASS->value, 'name' => 'FLOAT'],
            ['section' => Section::GLASS->value, 'name' => 'FLOAT', 'position' => 10],
        );

        /** @var Product $product */
        $product = Product::query()->firstOrCreate(
            ['section' => Section::GLASS->value, 'name' => $name],
            [
                'product_group_id' => $group->id,
                'section' => Section::GLASS->value,
                'name' => $name,
                'unit' => Unit::SQUARE_METER->value,
                'vat_rate' => 23,
            ],
        );

        ProductGlass::query()->firstOrCreate(
            ['product_id' => $product->id],
            ['product_id' => $product->id, 'thickness_mm' => 8.0],
        );

        return $product;
    }
}
