<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\Orders\OrderValue;
use App\Services\Orders\OrderListService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Listy zlecenia.
 *
 * Lista obsługuje dwa przypadki naraz: kompozycję (kilka pomieszczeń,
 * wszystkie wliczone) i wariantowanie oferty (alternatywy, jedna
 * wliczona). Testy pilnują tego, na czym traci się pieniądze albo
 * pracę: żeby alternatywa nie doliczyła się do kwoty i żeby kasowanie
 * listy nie zabrało wyceny bez śladu.
 */
class OrderListTest extends TestCase
{
    use RefreshDatabase;

    private OrderListService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $this->service = new OrderListService();
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Listy ' . random_int(1000, 9999),
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

    private function item(OrderList $list, string $amount = '1000.00'): OrderItem
    {
        /** @var OrderItem */
        return OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 8mm',
            'quantity' => 1,
            'unit_net_price' => $amount,
            'amount' => $amount,
        ]);
    }

    private function firstList(Order $order): OrderList
    {
        /** @var OrderList */
        return OrderList::query()
            ->where('order_id', $order->getKey())
            ->orderBy('number')
            ->firstOrFail();
    }

    #[Test]
    public function druga_lista_dostaje_kolejny_numer(): void
    {
        $order = $this->order();

        $result = $this->service->save((int) $order->getKey(), ['name' => 'Łazienka']);

        $this->assertSame([], $result['errors']);
        $this->assertSame(2, (int) OrderList::query()->findOrFail($result['id'])->number);
    }

    #[Test]
    public function alternatywa_zaklada_sie_wylaczona_z_kwoty(): void
    {
        $order = $this->order();

        $result = $this->service->save((int) $order->getKey(), [
            'name' => 'Wariant 6 mm',
            'role' => 'alternative',
        ]);

        /** @var OrderList $list */
        $list = OrderList::query()->findOrFail($result['id']);

        // Wariant, ktory dolicza sie do kwoty w chwili powstania, to
        // dokladnie ten blad, przed ktorym rola ma chronic.
        $this->assertFalse($list->is_included);
    }

    #[Test]
    public function wylaczona_lista_nie_wchodzi_do_wartosci_zlecenia(): void
    {
        $order = $this->order();
        $this->item($this->firstList($order));

        $added = $this->service->save((int) $order->getKey(), [
            'name' => 'Wariant',
            'role' => 'alternative',
        ]);

        /** @var OrderList $alternative */
        $alternative = OrderList::query()->findOrFail($added['id']);
        $this->item($alternative, '4000.00');

        $totals = (new OrderValue())->totals($order->fresh(['lists.items.processes']) ?? $order);

        // Klient ma dostac 1000, a nie sume dwoch wariantow.
        $this->assertSame('1000.00', $totals->net);
        $this->assertSame('4000.00', $totals->excludedNet);
    }

    #[Test]
    public function usuniecie_listy_nie_przenumerowuje_pozostalych(): void
    {
        $order = $this->order();

        $second = $this->service->save((int) $order->getKey(), ['name' => 'Druga']);
        $third = $this->service->save((int) $order->getKey(), ['name' => 'Trzecia']);

        $this->service->delete((int) $order->getKey(), (int) $second['id']);

        // „Lista 3" w komentarzu produkcyjnym ma dalej znaczyc te sama
        // liste po skasowaniu drugiej. Numer kolejnej idzie od
        // najwyzszego istniejacego, wiec po usunieciu tej z konca moze
        // sie powtorzyc — i to jest nieszkodliwe, bo skasowac da sie
        // tylko liste pusta.
        $this->assertSame(3, (int) OrderList::query()->findOrFail($third['id'])->number);
        $this->assertSame(1, (int) $this->firstList($order)->number);
    }

    #[Test]
    public function listy_z_pozycjami_nie_da_sie_skasowac(): void
    {
        $order = $this->order();
        $added = $this->service->save((int) $order->getKey(), ['name' => 'Druga']);

        /** @var OrderList $list */
        $list = OrderList::query()->findOrFail($added['id']);
        $this->item($list);

        $result = $this->service->delete((int) $order->getKey(), (int) $list->getKey());

        // Od wylaczania wariantu jest `is_included`. Kasowanie zabraloby
        // wycene bez sladu.
        $this->assertArrayHasKey('list', $result['errors']);
        $this->assertSame(2, OrderList::query()->where('order_id', $order->getKey())->count());
    }

    #[Test]
    public function ostatniej_listy_nie_da_sie_skasowac(): void
    {
        $order = $this->order();

        $result = $this->service->delete(
            (int) $order->getKey(),
            (int) $this->firstList($order)->getKey(),
        );

        // Zlecenie bez ani jednej listy nie ma gdzie trzymac pozycji.
        $this->assertArrayHasKey('list', $result['errors']);
    }

    #[Test]
    public function cudzej_listy_nie_da_sie_ruszyc(): void
    {
        $mine = $this->order();
        $other = $this->order();

        $result = $this->service->save(
            (int) $mine->getKey(),
            ['name' => 'Podmiana'],
            (int) $this->firstList($other)->getKey(),
        );

        $this->assertArrayHasKey('list', $result['errors']);
    }

    #[Test]
    public function pozycja_przenosi_sie_miedzy_listami(): void
    {
        $order = $this->order();
        $item = $this->item($this->firstList($order));

        $added = $this->service->save((int) $order->getKey(), ['name' => 'Druga']);

        $result = $this->service->moveItem(
            (int) $order->getKey(),
            (int) $item->getKey(),
            $added['id'],
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame(
            (int) $added['id'],
            (int) OrderItem::query()->findOrFail($item->getKey())->order_list_id,
        );
    }

    #[Test]
    public function pozycji_nie_da_sie_przeniesc_na_cudza_liste(): void
    {
        $mine = $this->order();
        $other = $this->order();
        $item = $this->item($this->firstList($mine));

        $result = $this->service->moveItem(
            (int) $mine->getKey(),
            (int) $item->getKey(),
            (int) $this->firstList($other)->getKey(),
        );

        $this->assertArrayHasKey('order_list_id', $result['errors']);
    }

    #[Test]
    public function przeniesienie_nie_rusza_ceny(): void
    {
        $order = $this->order();
        $item = $this->item($this->firstList($order), '1234.00');
        $added = $this->service->save((int) $order->getKey(), ['name' => 'Druga']);

        $this->service->moveItem(
            (int) $order->getKey(),
            (int) $item->getKey(),
            $added['id'],
        );

        // Przeniesienie nie zmienia ani materialu, ani wymiaru, wiec nie
        // ma powodu przeliczac wyceny i kasowac jej sladu.
        $this->assertSame('1234.00', OrderItem::query()->findOrFail($item->getKey())->amount);
    }

    #[Test]
    public function wstrzymanie_listy_to_nie_wylaczenie_z_kwoty(): void
    {
        $order = $this->order();
        $list = $this->firstList($order);
        $this->item($list);

        $this->service->save((int) $order->getKey(), [
            'is_included' => true,
            'is_on_hold' => true,
        ], (int) $list->getKey());

        $totals = (new OrderValue())->totals($order->fresh(['lists.items.processes']) ?? $order);

        // Wstrzymana nalezy do zlecenia i jest wyceniona — blokuje tylko
        // przekazanie na produkcje.
        $this->assertTrue(OrderList::query()->findOrFail($list->getKey())->is_on_hold);
        $this->assertSame('1000.00', $totals->net);
    }
}
