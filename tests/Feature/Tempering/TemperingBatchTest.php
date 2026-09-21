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
use App\Enum\TemperingItemStatus;
use App\Enum\TemperingBatchStatus;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Tempering\TemperingBoard;
use App\Services\Tempering\TemperingQueue;
use App\Services\Tempering\TemperingBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Partia do hartowni: wysyłka, powrót, stłuczka, rozliczenie.
 *
 * Najważniejsza asercja w tym pliku: **stłuczka zamyka pętlę**.
 * W starym systemie formatka po prostu nie wracała i zlecenie stało
 * bez wyjaśnienia (`20-hartownia.md` §4).
 */
class TemperingBatchTest extends TestCase
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
    public function partia_dostaje_kolejny_numer(): void
    {
        $supplier = $this->supplier();

        $first = $this->service->draft($supplier);
        $second = $this->service->draft($supplier);

        $this->assertSame($first->number + 1, $second->number);
    }

    #[Test]
    public function pustej_partii_nie_da_sie_wyslac(): void
    {
        $batch = $this->service->draft($this->supplier());

        $this->expectException(RuntimeException::class);
        $this->service->send($batch);
    }

    #[Test]
    public function wyslanie_przestawia_pozycje_na_wyslane(): void
    {
        [$batch, $item] = $this->prepared();

        $this->service->send($batch);

        $this->assertSame(TemperingBatchStatus::SENT, $batch->refresh()->status);
        $this->assertSame(TemperingItemStatus::SENT, $item->refresh()->status);
    }

    #[Test]
    public function pozycja_w_innej_partii_nie_przenosi_sie_po_cichu(): void
    {
        // To jest szklo lezace fizycznie gdzie indziej. Przeniesienie
        // bez slowa zgubiloby te informacje.
        [$batch, $item] = $this->prepared();

        $second = $this->service->draft($this->supplier('Druga hartownia'));
        $result = $this->service->add($second, [(int) $item->getKey()]);

        $this->assertSame(0, $result['added']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('innej partii', $result['skipped'][0]['reason']);
    }

    #[Test]
    public function powrot_bez_wskazan_traktuje_wszystko_jako_wrocone(): void
    {
        [$batch, $item] = $this->prepared();
        $this->service->send($batch);

        $result = $this->service->receive($batch, []);

        $this->assertSame(1, $result['returned']);
        $this->assertSame([], $result['replaced']);
        $this->assertSame(TemperingItemStatus::RETURNED, $item->refresh()->status);
        $this->assertSame(TemperingBatchStatus::RETURNED, $batch->refresh()->status);
    }

    #[Test]
    public function stluczka_zaklada_pozycje_zastepcza_w_kolejce(): void
    {
        [$batch, $item] = $this->prepared(quantity: 4);
        $this->service->send($batch);

        $result = $this->service->receive($batch, [
            (int) $item->getKey() => TemperingItemStatus::BROKEN->value,
        ]);

        $this->assertCount(1, $result['replaced']);

        $replacement = $result['replaced'][0];

        $this->assertSame(TemperingItemStatus::QUEUED, $replacement->status);
        $this->assertSame(4.0, (float) $replacement->quantity);
        // Wskazanie na stluczona pozycje: inaczej po miesiacu nie da sie
        // powiedziec, dlaczego ta sama formatka jechala dwa razy.
        $this->assertSame((int) $item->getKey(), $replacement->replaces_id);
        $this->assertNull($replacement->tempering_batch_id);
    }

    #[Test]
    public function poprawka_nie_zaklada_pozycji_zastepczej(): void
    {
        // Szyba wrocila, tylko wymaga czegos jeszcze. Nie jedzie do
        // pieca drugi raz jako nowa sztuka.
        [$batch, $item] = $this->prepared();
        $this->service->send($batch);

        $result = $this->service->receive($batch, [
            (int) $item->getKey() => TemperingItemStatus::REWORK->value,
        ]);

        $this->assertSame([], $result['replaced']);
        $this->assertSame(1, $result['returned']);
    }

    #[Test]
    public function uzgodnienie_po_stluczce_nie_dopisuje_drugiej_pozycji(): void
    {
        // Pozycja zastepcza juz pokrywa niedobor, wiec `sync` nie ma
        // czego dokladac. Gdyby mial, kazde wejscie na produkcje po
        // stluczce mnozylo liczbe szyb do pieca.
        $order = $this->order();
        $item = $this->addPane($order, quantity: 4);
        $this->queue->sync($order);

        $batch = $this->service->draft($this->supplier());
        /** @var TemperingItem $position */
        $position = TemperingItem::query()->firstOrFail();
        $this->service->add($batch, [(int) $position->getKey()]);
        $this->service->send($batch);
        $this->service->receive($batch, [
            (int) $position->getKey() => TemperingItemStatus::BROKEN->value,
        ]);

        $created = $this->queue->sync($order);

        $this->assertSame([], $created);
        $this->assertSame(2, TemperingItem::query()->count());
        $this->assertSame(4.0, (float) $item->fresh()?->quantity);
    }

    #[Test]
    public function anulowanie_szkicu_zwraca_pozycje_do_kolejki(): void
    {
        [$batch, $item] = $this->prepared();

        $this->service->cancel($batch);

        $fresh = $item->refresh();

        $this->assertNull($fresh->tempering_batch_id);
        $this->assertSame(TemperingItemStatus::QUEUED, $fresh->status);
    }

    #[Test]
    public function wyslanej_partii_nie_da_sie_anulowac(): void
    {
        [$batch] = $this->prepared();
        $this->service->send($batch);

        $this->expectException(RuntimeException::class);
        $this->service->cancel($batch);
    }

    #[Test]
    public function rozliczenie_zapisuje_koszt_ale_nie_rusza_wyceny(): void
    {
        [$batch, $item] = $this->prepared();
        $this->service->send($batch);
        $this->service->receive($batch, []);

        $before = $item->item?->amount;

        $this->service->settle($batch, '480.00', 'FV 12/2026');

        $this->assertSame('480.00', $batch->refresh()->net_cost);
        $this->assertSame(TemperingBatchStatus::SETTLED, $batch->status);
        // H-08 otwarte: koszt hartowania nie wchodzi do kwoty zlecenia.
        $this->assertSame($before, $item->item?->fresh()?->amount);
    }

    #[Test]
    public function dni_poza_zakladem_licza_sie_od_wysylki(): void
    {
        [$batch] = $this->prepared();

        $this->service->send($batch, Carbon::today()->subDays(6));

        $this->assertSame(6, $batch->refresh()->daysOut());
    }

    #[Test]
    public function kolejka_podaje_wage_powierzchnie_i_grubosci(): void
    {
        $order = $this->order();
        $this->addPane($order, quantity: 2);
        $this->queue->sync($order);

        $board = (new TemperingBoard())->queue();

        // 1,5 x 1,0 m, 2 szt., 8 mm: 3 m2 i 3 x 20 kg.
        $this->assertSame(3.0, $board['summary']['m2']);
        $this->assertSame(60.0, $board['summary']['kg']);
        $this->assertSame([8.0], $board['thicknesses']);
    }

    /**
     * @return array{0: \App\Models\TemperingBatch, 1: TemperingItem}
     */
    private function prepared(float $quantity = 1): array
    {
        $order = $this->order();
        $this->addPane($order, quantity: $quantity);
        $this->queue->sync($order);

        $batch = $this->service->draft($this->supplier());

        /** @var TemperingItem $position */
        $position = TemperingItem::query()->orderByDesc('id')->firstOrFail();

        $this->service->add($batch, [(int) $position->getKey()]);

        return [$batch, $position->refresh()];
    }

    private function supplier(string $name = 'Hartownia Stobno'): Supplier
    {
        /** @var Supplier */
        return Supplier::query()->firstOrCreate(['name' => $name], ['name' => $name]);
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
