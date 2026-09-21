<?php

declare(strict_types=1);

namespace Tests\Feature\Tempering;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\Product;
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
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use App\Models\OrderItemProcess;
use App\Enum\TemperingItemStatus;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Tempering\TemperingBoard;
use App\Services\Tempering\TemperingQueue;
use App\Services\Production\ProductionPlan;
use App\Services\Production\ProductionQueue;
use App\Services\Tempering\TemperingBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Sprzężenie hartowni z produkcją.
 *
 * Reguła, której pilnuje ten plik: **etap podzlecany zamyka powrót
 * od podwykonawcy, a nie hala** — i zamyka go dopiero wtedy, gdy dla
 * pozycji nie zostało nic w piecu. Dzięki temu zlecenie nie przejdzie
 * na „Gotowe" przy szkle, którego nie ma. W starym systemie zlecenie
 * 16492 miało dokładnie taki status.
 */
class SubcontractedWorkTest extends TestCase
{
    use RefreshDatabase;

    private TemperingQueue $queue;
    private TemperingBatchService $batches;
    private ProductionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new TemperingQueue();
        $this->batches = new TemperingBatchService();
        $this->plan = new ProductionPlan();
    }

    #[Test]
    public function hala_nie_widzi_etapu_podzlecanego(): void
    {
        [$order] = $this->prepared(quantity: 2);

        $board = (new ProductionQueue())->board();

        $codes = array_map(
            static fn(array $row): mixed => $row['process_code'] ?? null,
            $board['rows'],
        );

        $this->assertNotContains('H', $codes);
        // Zadanie mimo to istnieje — inaczej zlecenie przeszloby dalej.
        $this->assertTrue(
            ProductionTask::query()
                ->where('order_id', $order->getKey())
                ->whereIn('process_id', [$this->process()->id])
                ->exists(),
        );
    }

    #[Test]
    public function zlecenie_nie_jest_gotowe_zanim_szklo_wroci(): void
    {
        [$order] = $this->prepared(quantity: 2);

        $this->assertFalse($this->plan->allDone($order));
    }

    #[Test]
    public function powrot_zamyka_etap_i_zlecenie_moze_isc_dalej(): void
    {
        [$order, $batch, $position] = $this->prepared(quantity: 2);

        $this->batches->send($batch);
        $result = $this->batches->receive($batch, [
            (int) $position->getKey() => TemperingItemStatus::RETURNED->value,
        ]);

        $this->assertSame(1, $result['closed']);
        $this->assertTrue($this->plan->allDone($order));
    }

    #[Test]
    public function stluczka_nie_zamyka_etapu(): void
    {
        // Najwazniejsza asercja tego pliku. Szklo sie stluklo, wiec
        // pozycja zastepcza czeka w kolejce — etap ma zostac otwarty.
        [$order, $batch, $position] = $this->prepared(quantity: 2);

        $this->batches->send($batch);
        $result = $this->batches->receive($batch, [
            (int) $position->getKey() => TemperingItemStatus::BROKEN->value,
        ]);

        $this->assertSame(0, $result['closed']);
        $this->assertFalse($this->plan->allDone($order));
    }

    #[Test]
    public function czesciowy_powrot_nie_zamyka_etapu(): void
    {
        // Formatka na 24 sztuki jedzie w dwoch partiach. Etap jest
        // jeden na cala pozycje, wiec pierwsza partia go nie zamyka.
        [$order] = $this->prepared(quantity: 24, batch: false);

        /** @var TemperingItem $whole */
        $whole = TemperingItem::query()->firstOrFail();
        $whole->quantity = '10.000';
        $whole->save();

        /** @var TemperingItem $rest */
        $rest = TemperingItem::query()->create([
            'order_item_id' => $whole->order_item_id,
            'status' => TemperingItemStatus::QUEUED->value,
            'quantity' => '14.000',
        ]);

        $first = $this->batches->draft($this->supplier());
        $this->batches->add($first, [(int) $whole->getKey()]);
        $this->batches->send($first);
        $result = $this->batches->receive($first, []);

        $this->assertSame(0, $result['closed']);
        $this->assertFalse($this->plan->allDone($order));

        $second = $this->batches->draft($this->supplier());
        $this->batches->add($second, [(int) $rest->getKey()]);
        $this->batches->send($second);
        $second = $this->batches->receive($second, []);

        $this->assertSame(1, $second['closed']);
        $this->assertTrue($this->plan->allDone($order));
    }

    #[Test]
    public function ponowna_wysylka_otwiera_zamkniety_etap(): void
    {
        // Pozycja zastepcza jedzie do pieca przy juz zamknietym etapie
        // (np. czesc wrocila i etap sie domknal, a potem doszla
        // poprawka). „Zrobione" przy szybie, ktorej nie ma, to falsz.
        [$order, $batch, $position] = $this->prepared(quantity: 2);

        $this->batches->send($batch);
        $this->batches->receive($batch, []);

        $this->assertTrue($this->plan->allDone($order));

        /** @var TemperingItem $again */
        $again = TemperingItem::query()->create([
            'order_item_id' => $position->order_item_id,
            'status' => TemperingItemStatus::QUEUED->value,
            'quantity' => '2.000',
        ]);

        $next = $this->batches->draft($this->supplier());
        $this->batches->add($next, [(int) $again->getKey()]);
        $this->batches->send($next);

        $this->assertFalse($this->plan->allDone($order));
    }

    #[Test]
    public function karta_zlecenia_wie_ze_czeka_u_podwykonawcy(): void
    {
        [$order, $batch] = $this->prepared(quantity: 2);

        $this->batches->send($batch);

        $state = (new TemperingBoard())->forOrder($order->refresh());

        $this->assertNotNull($state);
        $this->assertTrue($state['is_waiting']);
        $this->assertSame(2.0, $state['sent']);
        $this->assertCount(1, $state['batches']);
    }

    #[Test]
    public function zlecenie_bez_hartowania_nie_dostaje_paska(): void
    {
        $order = $this->order();

        $this->assertNull((new TemperingBoard())->forOrder($order));
    }

    #[Test]
    public function koszt_partii_rozksiegowuje_sie_po_m2(): void
    {
        [, $batch] = $this->prepared(quantity: 2);

        $this->batches->send($batch);
        $this->batches->receive($batch, []);
        $this->batches->settle($batch, '300.00');

        $card = (new TemperingBoard())->card($batch->refresh());

        // Jedna pozycja, wiec caly koszt na nia. Rozksiegowanie jest
        // kosztem, nie cena — klient placi z cennika procesu H.
        $this->assertSame('300.00', $card['items'][0]['cost_share']);
    }

    /**
     * @return array{0: Order, 1: \App\Models\TemperingBatch, 2: TemperingItem}
     */
    private function prepared(float $quantity, bool $batch = true): array
    {
        $order = $this->order();
        $item = $this->addPane($order, $quantity);
        $this->addProcess($item);

        $this->plan->sync($order);
        $this->queue->sync($order);

        /** @var TemperingItem $position */
        $position = TemperingItem::query()->orderByDesc('id')->firstOrFail();

        if (!$batch) {
            return [$order, $this->batches->draft($this->supplier()), $position];
        }

        $draft = $this->batches->draft($this->supplier());
        $this->batches->add($draft, [(int) $position->getKey()]);

        return [$order, $draft, $position->refresh()];
    }

    private function process(): Process
    {
        /** @var Process */
        return Process::query()->where('code', 'H')->firstOrFail();
    }

    private function addProcess(OrderItem $item): void
    {
        OrderItemProcess::query()->create([
            'order_item_id' => $item->id,
            'process_id' => $this->process()->id,
            'position' => 1,
            'days' => 8,
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

    private function addPane(Order $order, float $quantity): OrderItem
    {
        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();

        $product = $this->glass();

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

    private function glass(): Product
    {
        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::GLASS->value, 'name' => 'FLOAT'],
            ['section' => Section::GLASS->value, 'name' => 'FLOAT', 'position' => 10],
        );

        /** @var Product $product */
        $product = Product::query()->firstOrCreate(
            ['section' => Section::GLASS->value, 'name' => 'float 8mm hartowany'],
            [
                'product_group_id' => $group->id,
                'section' => Section::GLASS->value,
                'name' => 'float 8mm hartowany',
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
