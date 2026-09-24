<?php

declare(strict_types=1);

namespace Tests\Feature\Tempering;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\OrderPane;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\ProductGlass;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\ProductGroup;
use App\Models\TemperingItem;
use App\Enum\TemperingItemStatus;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Tempering\TemperingQueue;
use App\Services\Tempering\TemperingBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Kolejka do hartowni.
 *
 * Reguła, której pilnują te testy: kolejka jest **uzgadniana**, a nie
 * dopisywana. Powrót zlecenia na produkcję po poprawce nie zakłada
 * drugiego kompletu szyb do pieca.
 */
class TemperingQueueTest extends TestCase
{
    use RefreshDatabase;

    private TemperingQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new TemperingQueue();
    }

    #[Test]
    public function do_kolejki_trafia_szyba_oznaczona_jako_hartowana(): void
    {
        $order = $this->order();
        $this->addPane($order, tempered: true, quantity: 4);

        $this->queue->sync($order);

        $this->assertSame(1, TemperingItem::query()->count());
        $this->assertSame(4.0, (float) TemperingItem::query()->first()?->quantity);
    }

    #[Test]
    public function szyba_niehartowana_nie_trafia_do_kolejki(): void
    {
        $order = $this->order();
        $this->addPane($order, tempered: false, quantity: 4);

        $this->queue->sync($order);

        $this->assertSame(0, TemperingItem::query()->count());
    }

    #[Test]
    public function alternatywa_nie_jedzie_do_pieca(): void
    {
        // Wariant oferty nie wchodzi ani do produkcji, ani do kwoty,
        // wiec nie ma po co jechac do hartowni.
        $order = $this->order(included: false);
        $this->addPane($order, tempered: true, quantity: 4);

        $this->queue->sync($order);

        $this->assertSame(0, TemperingItem::query()->count());
    }

    #[Test]
    public function powtorne_uzgodnienie_nie_dubluje_pozycji(): void
    {
        $order = $this->order();
        $this->addPane($order, tempered: true, quantity: 4);

        $this->queue->sync($order);
        $this->queue->sync($order);
        $this->queue->sync($order);

        $this->assertSame(1, TemperingItem::query()->count());
        $this->assertSame(4.0, (float) TemperingItem::query()->sum('quantity'));
    }

    #[Test]
    public function zwiekszenie_ilosci_dopisuje_brakujace_sztuki(): void
    {
        $order = $this->order();
        $item = $this->addPane($order, tempered: true, quantity: 4);

        $this->queue->sync($order);

        $item->quantity = '6.000';
        $item->save();

        $this->queue->sync($order);

        $this->assertSame(6.0, (float) TemperingItem::query()->sum('quantity'));
    }

    #[Test]
    public function zmniejszenie_ilosci_zdejmuje_nadmiar_z_kolejki(): void
    {
        $order = $this->order();
        $item = $this->addPane($order, tempered: true, quantity: 6);

        $this->queue->sync($order);

        $item->quantity = '2.000';
        $item->save();

        $this->queue->sync($order);

        $this->assertSame(2.0, (float) TemperingItem::query()->sum('quantity'));
    }

    #[Test]
    public function stluczka_odtwarza_niedobor_przy_kolejnym_uzgodnieniu(): void
    {
        // Pozycja stluczona nie pokrywa niczego, wiec uzgodnienie
        // widzi brak i dopisuje nowa. Ta sama droga, ktora jawnie
        // przechodzi `TemperingQueue::replace()` przy odbiorze partii.
        $order = $this->order();
        $this->addPane($order, tempered: true, quantity: 4);

        $this->queue->sync($order);

        /** @var TemperingItem $position */
        $position = TemperingItem::query()->firstOrFail();
        $position->status = TemperingItemStatus::BROKEN;
        $position->save();

        $created = $this->queue->sync($order);

        $this->assertCount(1, $created);
        $this->assertSame(4.0, (float) $created[0]->quantity);
        $this->assertSame(2, TemperingItem::query()->count());
    }

    #[Test]
    public function anulowanie_zdejmuje_z_kolejki_tylko_to_co_nie_pojechalo(): void
    {
        $order = $this->order();
        $this->addPane($order, tempered: true, quantity: 4);
        $this->addPane($order, tempered: true, quantity: 2, name: 'druga szyba');

        $this->queue->sync($order);

        /** @var TemperingItem $sent */
        $sent = TemperingItem::query()->orderBy('id')->firstOrFail();
        $sent->status = TemperingItemStatus::SENT;
        $sent->save();

        $this->queue->release($order);

        // Szklo u podwykonawcy wroci niezaleznie od losow zlecenia.
        $this->assertSame(1, TemperingItem::query()->count());
        $this->assertSame(
            TemperingItemStatus::SENT,
            TemperingItem::query()->first()?->status,
        );
    }

    #[Test]
    public function kafelek_pieca_liczy_to_samo_co_kolejka(): void
    {
        $first = $this->order();
        $this->addPane($first, tempered: true, quantity: 2);
        $this->addPane($first, tempered: true, quantity: 1);
        $this->queue->sync($first);

        $second = $this->order();
        $this->addPane($second, tempered: true, quantity: 3);
        $this->queue->sync($second);

        $board = new TemperingBoard();

        // Pulpit pyta o sama liczbe. Musi to byc liczba ekranu hartowni,
        // liczona z tego samego zbioru, a nie z osobnego zapytania.
        $this->assertSame(3, $board->count());
        $this->assertSame($board->queue()['summary']['shown'], $board->count());
    }

    private function order(bool $included = true): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Hartownia ' . random_int(1000, 9999),
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
            'role' => $included ? 'component' : 'alternative',
            'is_included' => $included,
        ]);

        return $order->fresh() ?? $order;
    }

    private function addPane(
        Order $order,
        bool $tempered,
        float $quantity,
        string $name = 'float 8mm',
    ): OrderItem {
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
            'is_tempered' => $tempered,
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
