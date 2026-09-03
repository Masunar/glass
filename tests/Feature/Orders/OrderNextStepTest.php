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
use App\Models\InvoiceType;
use App\Services\Orders\OrderNextStep;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * „Co dalej" — najbardziej doniosła rzecz na liście zleceń.
 *
 * Stary system pokazywał listę statusów do wyboru i pozwalał wybrać
 * każdy, więc zlecenie potrafiło trafić do produkcji bez zaliczki.
 * Tutaj przejście jest dostępne dopiero, gdy warunki są spełnione —
 * a warunku, którego nie da się dziś sprawdzić, **nie przepuszczamy**.
 */
class OrderNextStepTest extends TestCase
{
    use RefreshDatabase;

    private OrderNextStep $steps;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();

        Order::query()->delete();

        $this->steps = new OrderNextStep();
    }

    private function order(string $statusCode, array $attributes = []): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, $statusCode);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Testowa sp. z o.o.',
            'tax_id' => '6532694845',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            ...$attributes,
        ]);

        return $order;
    }

    private function withList(Order $order, string $amount = '500.00', bool $onHold = false): Order
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
            'is_on_hold' => $onHold,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 6mm',
            'quantity' => 1,
            'unit_net_price' => $amount,
            'amount' => $amount,
        ]);

        return $order->fresh(['lists.items.processes']) ?? $order;
    }

    private function step(Order $order, string $toCode): ?object
    {
        foreach ($this->steps->forOrder($order) as $step) {
            if ($step->target->code === $toCode) {
                return $step;
            }
        }

        return null;
    }

    #[Test]
    public function przejscie_bez_wypelnionych_warunkow_jest_zablokowane(): void
    {
        $order = $this->order('DO_WYCENY');

        $step = $this->step($order, 'ZLECENIE');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        // Blokuje pierwszy niespelniony warunek, nie ostatni — czlowiek
        // ma dostac jedna rzecz do zrobienia, nie liste czterech.
        $this->assertSame('Zlecenie nie ma żadnej włączonej listy.', $step->blockedBy);
    }

    #[Test]
    public function komplet_warunkow_odblokowuje_przejscie(): void
    {
        /** @var InvoiceType $invoiceType */
        $invoiceType = InvoiceType::query()->create([
            'name' => 'VAT 23%',
            'vat_rate' => 23,
        ]);

        $order = $this->withList($this->order('DO_WYCENY', [
            'invoice_type_id' => $invoiceType->id,
        ]));

        $step = $this->step($order, 'ZLECENIE');

        $this->assertNotNull($step);
        $this->assertTrue($step->available, (string) $step->blockedBy);
    }

    #[Test]
    public function warunku_bez_modulu_nie_przepuszczamy(): void
    {
        // Przekazanie do produkcji zalezy od zaliczki i limitu kredytowego,
        // a modulu wplat nie ma. Gdyby brak modulu znaczyl "warunek
        // spelniony", zlecenie trafialoby do produkcji bez zaliczki —
        // czyli dokladnie to, przed czym ten mechanizm ma chronic.
        $order = $this->withList($this->order('ZLECENIE'));

        $step = $this->step($order, 'PRODUKCJA');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertTrue($step->unknown);
        $this->assertStringContainsString('jeszcze', (string) $step->blockedBy);
    }

    #[Test]
    public function sposob_wydania_decyduje_o_dostepnej_sciezce(): void
    {
        $order = $this->withList($this->order('GOTOWE', [
            'delivery_method' => DeliveryMethod::INSTALLATION->value,
        ]));

        // Warunek przejscia uzywal wartosci "assembly", a enum ma
        // "installation" — sciezka do montazu nie byla dostepna nigdy.
        $installation = $this->step($order, 'MONTAZ');
        $delivery = $this->step($order, 'DOSTAWA');

        $this->assertNotNull($installation);
        $this->assertTrue($installation->available, (string) $installation->blockedBy);
        $this->assertNotNull($delivery);
        $this->assertFalse($delivery->available);
    }

    #[Test]
    public function odbior_wymaga_wskazanego_punktu(): void
    {
        $order = $this->withList($this->order('GOTOWE', [
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]));

        $step = $this->step($order, 'ODBIOR');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertSame('Nie wskazano punktu odbioru.', $step->blockedBy);
    }

    #[Test]
    public function wstrzymana_lista_zatrzymuje_zlecenie(): void
    {
        $order = $this->withList($this->order('ZLECENIE'), onHold: true);

        $step = $this->step($order, 'PRODUKCJA');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        // Wstrzymana lista jest warunkiem sprawdzalnym, wiec musi
        // wyprzedzic ten, ktorego nie da sie sprawdzic.
        $this->assertFalse($step->unknown);
        $this->assertSame('Co najmniej jedna lista jest wstrzymana.', $step->blockedBy);
    }

    #[Test]
    public function pierwsze_dostepne_przejscie_pomija_zablokowane(): void
    {
        $order = $this->withList($this->order('GOTOWE', [
            'delivery_method' => DeliveryMethod::DELIVERY->value,
        ]));

        $first = $this->steps->firstAvailable($order);

        $this->assertNotNull($first);
        $this->assertSame('DOSTAWA', $first->target->code);
    }

    #[Test]
    public function firma_bez_nipu_nie_przechodzi_do_zlecenia(): void
    {
        /** @var InvoiceType $invoiceType */
        $invoiceType = InvoiceType::query()->create(['name' => 'VAT 23%', 'vat_rate' => 23]);

        $order = $this->withList($this->order('DO_WYCENY', [
            'invoice_type_id' => $invoiceType->id,
        ]));

        $order->contractor?->update(['tax_id' => null]);

        $step = $this->step($order->fresh(['lists.items.processes', 'contractor']), 'ZLECENIE');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertSame('Dane kontrahenta są niekompletne.', $step->blockedBy);
    }
}
