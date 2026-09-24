<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Enum\StatusDomain;
use App\Models\OrderDiscount;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Services\Orders\OrderValue;
use App\Services\Orders\OrderValueStore;
use App\Services\Orders\ContractorBalance;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wartość zlecenia zapamiętana na zleceniu.
 *
 * Pilnowane jest to, czego nie widać: że **każda zmiana, od której
 * zależy wartość, oznacza ją jako nieaktualną**. Zapamiętana kwota,
 * której nikt nie odświeżył, wygląda dokładnie tak samo jak prawdziwa,
 * a saldo kontrahenta liczone z niej przepuściłoby zlecenie ponad
 * limitem kupieckim bez żadnego objawu.
 */
class OrderValueStoreTest extends TestCase
{
    use RefreshDatabase;

    private OrderValueStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $this->store = new OrderValueStore();
    }

    #[Test]
    public function przeliczenie_daje_to_samo_co_order_value(): void
    {
        $order = $this->order('1000.00');

        $this->store->refreshStale();

        /** @var Order $fresh */
        $fresh = Order::query()->with(['lists.items.processes', 'discounts', 'invoiceType'])->findOrFail($order->getKey());
        $totals = (new OrderValue())->totals($fresh);

        // Baza pamieta wynik, liczy dalej wylacznie OrderValue.
        $this->assertFalse($fresh->value_stale);
        $this->assertSame($totals->net, $fresh->value_net);
        $this->assertSame($totals->gross, $fresh->value_gross);
    }

    #[Test]
    public function nowa_pozycja_oznacza_wartosc_jako_nieaktualna(): void
    {
        $order = $this->order('1000.00');
        $this->store->refreshStale();

        $this->item($this->firstList($order), '250.00');

        $this->assertTrue((bool) Order::query()->whereKey($order->getKey())->value('value_stale'));
    }

    #[Test]
    public function usuniecie_pozycji_oznacza_wartosc_jako_nieaktualna(): void
    {
        $order = $this->order('1000.00');
        $this->store->refreshStale();

        OrderItem::query()->where('order_list_id', $this->firstList($order)->getKey())->firstOrFail()->delete();

        $this->assertTrue((bool) Order::query()->whereKey($order->getKey())->value('value_stale'));
    }

    #[Test]
    public function rabat_oznacza_wartosc_jako_nieaktualna(): void
    {
        $order = $this->order('1000.00');
        $this->store->refreshStale();

        OrderDiscount::query()->create([
            'order_id' => $order->getKey(),
            'section' => Section::GLASS->value,
            'percent' => '10.00',
        ]);

        $this->assertTrue((bool) Order::query()->whereKey($order->getKey())->value('value_stale'));
    }

    #[Test]
    public function zmiana_typu_faktury_oznacza_wartosc_jako_nieaktualna(): void
    {
        $order = $this->order('1000.00');
        $this->store->refreshStale();

        /** @var InvoiceType $other */
        $other = InvoiceType::query()->where('id', '!=', $order->invoice_type_id)->firstOrFail();

        $fresh = Order::query()->findOrFail($order->getKey());
        $fresh->invoice_type_id = (int) $other->getKey();
        $fresh->save();

        $this->assertTrue((bool) Order::query()->whereKey($order->getKey())->value('value_stale'));
    }

    #[Test]
    public function zmiana_stawki_w_slowniku_oznacza_zlecenia_z_tym_typem(): void
    {
        $order = $this->order('1000.00');
        $this->store->refreshStale();

        /** @var InvoiceType $type */
        $type = InvoiceType::query()->findOrFail($order->invoice_type_id);
        $type->vat_rate = $type->vat_rate === 8 ? 23 : 8;
        $type->save();

        // Stawka ze slownika zmienia brutto kazdego zlecenia z tym typem
        // faktury — bez tego saldo liczyloby dlug po starej stawce.
        $this->assertTrue((bool) Order::query()->whereKey($order->getKey())->value('value_stale'));
    }

    #[Test]
    public function zapis_wartosci_nie_oznacza_jej_od_razu_jako_nieaktualnej(): void
    {
        $order = $this->order('1000.00');

        $this->store->refreshStale();

        // Zapis idzie zapytaniem, nie modelem. Gdyby szedl modelem,
        // zdarzenie zapisu oznaczyloby wartosc jako nieaktualna w tej
        // samej chwili, w ktorej zostala policzona.
        $this->assertFalse((bool) Order::query()->whereKey($order->getKey())->value('value_stale'));
    }

    #[Test]
    public function saldo_liczy_sie_z_wartosci_zlecen(): void
    {
        $first = $this->order('1000.00');
        $second = $this->order('500.00', $first->contractor_id);

        $expected = 0.0;
        $value = new OrderValue();

        foreach ([$first, $second] as $order) {
            /** @var Order $loaded */
            $loaded = Order::query()->with(['lists.items.processes', 'discounts', 'invoiceType'])->findOrFail($order->getKey());
            $totals = $value->totals($loaded);
            $expected += (float) ($totals->gross ?? $totals->net);
        }

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->findOrFail($first->contractor_id);

        // Saldo przelicza nieaktualne wartosci samo, przed suma —
        // swieze zlecenia nie potrzebuja zadnego osobnego kroku.
        $this->assertSame(
            number_format($expected, 2, '.', ''),
            (new ContractorBalance())->outstanding($contractor),
        );
    }

    private function order(string $amount, ?int $contractorId = null): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        if ($contractorId === null) {
            /** @var Contractor $contractor */
            $contractor = Contractor::query()->create([
                'type' => ContractorType::COMPANY->value,
                'name' => 'Wartość ' . random_int(1000, 9999),
                'credit_limit' => '100000.00',
            ]);
            $contractorId = (int) $contractor->getKey();
        }

        /** @var InvoiceType $invoiceType */
        $invoiceType = InvoiceType::query()->where('vat_rate', 23)->firstOrFail();

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractorId,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'invoice_type_id' => $invoiceType->getKey(),
        ]);

        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
        ]);

        $this->item($list, $amount);

        return $order;
    }

    private function item(OrderList $list, string $amount): void
    {
        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 6mm',
            'quantity' => 1,
            'unit_net_price' => $amount,
            'amount' => $amount,
        ]);
    }

    private function firstList(Order $order): OrderList
    {
        /** @var OrderList */
        return OrderList::query()->where('order_id', $order->getKey())->orderBy('number')->firstOrFail();
    }
}
