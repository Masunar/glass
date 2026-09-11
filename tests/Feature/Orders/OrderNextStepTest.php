<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Carbon\Carbon;
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
use App\Models\Payment;
use App\Models\InvoiceType;
use App\Models\CashRegister;
use App\Enum\PaymentChannel;
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

    /**
     * Nazwa typu faktury musi byc poza katalogiem: `DictionarySeeder`
     * odpalony przez inna klase testowa zostawia w bazie „VAT 23%",
     * a `name` jest unikalne.
     */
    private function invoiceType(): InvoiceType
    {
        /** @var InvoiceType */
        return InvoiceType::query()->create([
            'name' => 'Testowy typ ' . random_int(100000, 999999),
            'vat_rate' => 23,
        ]);
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

    /**
     * Oswiadczenie o komplecie rysunkow. W prawdziwym obiegu biuro
     * sklada je przed przekazaniem na produkcje, wiec testy o dalszych
     * warunkach musza zaczynac od tego samego miejsca.
     */
    private function withDrawings(Order $order): Order
    {
        $order->update([
            'drawings_complete_at' => Carbon::now(),
            'drawings_complete_by' => null,
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
        $order = $this->withList($this->order('DO_WYCENY', [
            'invoice_type_id' => $this->invoiceType()->id,
        ]));

        $step = $this->step($order, 'ZLECENIE');

        $this->assertNotNull($step);
        $this->assertTrue($step->available, (string) $step->blockedBy);
    }

    #[Test]
    public function warunku_bez_modulu_nie_przepuszczamy(): void
    {
        // Odrzucenie oferty wymaga powodu, a pola na powod nie ma.
        // Gdyby brak pola znaczyl "warunek spelniony", oferta dalaby sie
        // zamknac bez sladu, dlaczego klient jej nie przyjal — czyli
        // dokladnie to, przed czym ten mechanizm ma chronic.
        $order = $this->withList($this->order('DO_WYCENY'));

        $step = $this->step($order, 'OFERTA_ODRZUCONA');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertTrue($step->unknown);
        $this->assertStringContainsString('jeszcze', (string) $step->blockedBy);
    }

    #[Test]
    public function bez_zaliczki_i_bez_limitu_produkcja_stoi(): void
    {
        // Kontrahent z zerowym limitem i zlecenie bez wplaty: warunek
        // jest dzis sprawdzalny, wiec czlowiek dostaje konkretny powod,
        // a nie "poczekaj na modul".
        $order = $this->withDrawings($this->withList($this->order('ZLECENIE', [
            'invoice_type_id' => $this->invoiceType()->id,
        ])));

        $step = $this->step($order, 'PRODUKCJA');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertFalse($step->unknown);
        $this->assertSame(
            'Brak zaliczki, a kontrahent nie mieści się w limicie kredytowym.',
            $step->blockedBy,
        );
    }

    #[Test]
    public function sama_zaliczka_wystarczy_zeby_ruszyc_produkcje(): void
    {
        $order = $this->withDrawings($this->withList($this->order('ZLECENIE', [
            'invoice_type_id' => $this->invoiceType()->id,
        ])));

        /** @var CashRegister $register */
        $register = CashRegister::query()->create([
            'name' => 'Kasa testowa ' . random_int(1000, 9999),
            'channel' => PaymentChannel::CASH->value,
            'default_currency' => 'PLN',
            'is_active' => true,
            'position' => 1,
        ]);

        // Klient, ktory cos wplacil, potwierdzil zamowienie czynem —
        // limit kupiecki przestaje byc pytaniem.
        Payment::query()->create([
            'order_id' => $order->id,
            'cash_register_id' => $register->id,
            'amount' => '100.00',
            'currency' => 'PLN',
            'exchange_rate' => '1.000000',
            'amount_base' => '100.00',
            'paid_on' => Carbon::now()->toDateString(),
        ]);

        $step = $this->step(
            $order->fresh(['lists.items.processes', 'payments']) ?? $order,
            'PRODUKCJA',
        );

        $this->assertNotNull($step);
        $this->assertTrue($step->available, (string) $step->blockedBy);
    }

    #[Test]
    public function bez_typu_faktury_limit_jest_nierozstrzygalny(): void
    {
        // Limit kupiecki jest kwota brutto, a brutto bez stawki VAT nie
        // istnieje. To brak danej, nie brak modulu — i tak tez brzmi
        // zdanie, ktore dostaje czlowiek.
        $order = $this->withDrawings($this->withList($this->order('ZLECENIE')));

        $step = $this->step($order, 'PRODUKCJA');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertTrue($step->unknown);
        $this->assertStringContainsString('typu faktury', (string) $step->blockedBy);
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
        $order = $this->withDrawings(
            $this->withList($this->order('ZLECENIE'), onHold: true),
        );

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
        $order = $this->withList($this->order('DO_WYCENY', [
            'invoice_type_id' => $this->invoiceType()->id,
        ]));

        $order->contractor?->update(['tax_id' => null]);

        $step = $this->step($order->fresh(['lists.items.processes', 'contractor']), 'ZLECENIE');

        $this->assertNotNull($step);
        $this->assertFalse($step->available);
        $this->assertSame('Dane kontrahenta są niekompletne.', $step->blockedBy);
    }
}
