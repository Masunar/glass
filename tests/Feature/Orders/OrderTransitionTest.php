<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\StatusTransition;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use App\Services\Orders\OrderTransition;
use Database\Seeders\Core\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wykonanie przejścia statusu.
 *
 * Sedno tych testów: warunki sprawdzane są **drugi raz**, po stronie
 * serwera. Ekran pokazuje przycisk tylko przy przejściu dostępnym, ale
 * między narysowaniem listy a kliknięciem ktoś inny mógł wstrzymać
 * listę albo przesunąć zlecenie.
 */
class OrderTransitionTest extends TestCase
{
    use RefreshDatabase;

    private OrderTransition $transition;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();

        Order::query()->delete();

        $this->transition = new OrderTransition();
    }

    private function order(string $statusCode, string $delivery = DeliveryMethod::PICKUP->value): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, $statusCode);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Przejscie ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => $delivery,
        ]);

        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 6mm',
            'quantity' => 1,
            'unit_net_price' => '1000.00',
            'amount' => '1000.00',
        ]);

        return $order;
    }

    private function transitionId(string $from, string $to): int
    {
        /** @var Status $fromStatus */
        $fromStatus = Status::findByCode(StatusDomain::ORDER, $from);
        /** @var Status $toStatus */
        $toStatus = Status::findByCode(StatusDomain::ORDER, $to);

        /** @var StatusTransition $transition */
        $transition = StatusTransition::query()
            ->where('from_status_id', $fromStatus->id)
            ->where('to_status_id', $toStatus->id)
            ->firstOrFail();

        return (int) $transition->getKey();
    }

    #[Test]
    public function dostepne_przejscie_zmienia_status(): void
    {
        $order = $this->order('MONTAZ', DeliveryMethod::INSTALLATION->value);

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('MONTAZ', 'NIEROZLICZONE'),
        );

        $this->assertSame([], $result['errors']);
        $this->assertSame('NIEROZLICZONE', $result['status_code']);
        $this->assertSame(
            'NIEROZLICZONE',
            Order::query()->findOrFail($order->getKey())->status?->code,
        );
    }

    #[Test]
    public function przejscie_zostawia_slad_w_dzienniku(): void
    {
        $order = $this->order('ODBIOR');

        $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('ODBIOR', 'NIEROZLICZONE'),
        );

        /** @var AuditEntry $entry */
        $entry = AuditEntry::query()
            ->where('auditable_type', Order::class)
            ->where('auditable_id', (int) $order->getKey())
            ->firstOrFail();

        $this->assertSame('status_changed', $entry->event);
        $this->assertSame('ODBIOR', $entry->changes[0]['before']);
        $this->assertSame('NIEROZLICZONE', $entry->changes[0]['after']);
    }

    #[Test]
    public function przejscie_spoza_biezacego_statusu_jest_odrzucone(): void
    {
        $order = $this->order('ZLECENIE');

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('MONTAZ', 'NIEROZLICZONE'),
        );

        $this->assertNotSame([], $result['errors']);
        $this->assertSame(
            'ZLECENIE',
            Order::query()->findOrFail($order->getKey())->status?->code,
        );
    }

    #[Test]
    public function niespelniony_warunek_blokuje_i_niesie_powod(): void
    {
        // Zlecenie jest odbiorem wlasnym, wiec do montazu isc nie moze.
        $order = $this->order('GOTOWE');

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('GOTOWE', 'MONTAZ'),
        );

        $this->assertSame(
            ['Zlecenie nie jest oznaczone jako montaż.'],
            $result['errors'],
        );
    }

    #[Test]
    public function warunek_czekajacy_na_modul_blokuje_zamiast_przepuszczac(): void
    {
        $order = $this->order('ZLECENIE');

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('ZLECENIE', 'PRODUKCJA'),
        );

        $this->assertNotSame([], $result['errors']);
        $this->assertSame(
            'ZLECENIE',
            Order::query()->findOrFail($order->getKey())->status?->code,
        );
    }

    #[Test]
    public function anulowanie_bez_powodu_jest_zablokowane(): void
    {
        $order = $this->order('ZLECENIE');

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('ZLECENIE', 'ANULOWANE'),
        );

        $this->assertSame(['Podaj powód anulowania.'], $result['errors']);
    }

    #[Test]
    public function powod_podany_z_akcja_odblokowuje_anulowanie(): void
    {
        $order = $this->order('ZLECENIE');

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('ZLECENIE', 'ANULOWANE'),
            'klient zrezygnował po wycenie',
        );

        $this->assertSame([], $result['errors']);

        $fresh = Order::query()->findOrFail($order->getKey());

        $this->assertSame('ANULOWANE', $fresh->status?->code);
        $this->assertSame('klient zrezygnował po wycenie', $fresh->cancellation_reason);
    }

    #[Test]
    public function dostawa_przechodzi_do_nierozliczonych_bez_warunkow(): void
    {
        $order = $this->order('DOSTAWA', DeliveryMethod::DELIVERY->value);

        $result = $this->transition->run(
            (int) $order->getKey(),
            $this->transitionId('DOSTAWA', 'NIEROZLICZONE'),
        );

        $this->assertSame([], $result['errors']);
    }
}
