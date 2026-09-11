<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Payment;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Enum\PaymentChannel;
use App\Models\CashRegister;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\PaymentService;
use Database\Seeders\Core\RoleSeeder;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\LocationSeeder;
use App\Services\Orders\ContractorBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wpłaty, saldo i limit kupiecki.
 *
 * Sedno: wpłaty się nie usuwa. Korekta jest osobnym wierszem z kwotą
 * ujemną, więc suma wpłat wychodzi sama, a historia pieniędzy zostaje
 * w całości — to ona jest dowodem przy sporze z klientem.
 *
 * Drugie sedno: kwota przeliczona na złotówki jest zapisana, nie
 * liczona przy odczycie. Kurs wpisany przy wpłacie ma zostać taki, jaki
 * był, choćby jutro ktoś poprawił tabelę kursów.
 */
class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();

        Order::query()->delete();

        $this->service = new PaymentService();
    }

    /**
     * Typ faktury zakładany osobno dla każdego testu: słownik bywa już
     * wypełniony przez inną klasę testową i „VAT 23%" potrafi się zderzyć
     * z unikalną nazwą.
     */
    private function invoiceType(int $rate = 23): InvoiceType
    {
        /** @var InvoiceType */
        return InvoiceType::query()->create([
            'name' => 'Wpłaty ' . random_int(100000, 999999),
            'vat_rate' => $rate,
        ]);
    }

    private function register(string $currency = 'PLN'): CashRegister
    {
        /** @var CashRegister */
        return CashRegister::query()->create([
            'name' => 'Kasa ' . $currency . ' ' . random_int(1000, 9999),
            'channel' => PaymentChannel::TRANSFER->value,
            'default_currency' => $currency,
            'is_active' => true,
            'position' => 1,
        ]);
    }

    /** Zlecenie na 1000 netto; z VAT 23 % daje 1230 brutto. */
    private function order(bool $withInvoiceType = true, ?Contractor $contractor = null): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        $contractor ??= $this->contractor();

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'invoice_type_id' => $withInvoiceType ? $this->invoiceType()->id : null,
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
            'name' => 'float 8mm',
            'quantity' => 1,
            'unit_net_price' => '1000.00',
            'amount' => '1000.00',
        ]);

        return $order;
    }

    private function contractor(float $creditLimit = 0.0): Contractor
    {
        /** @var Contractor */
        return Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Płatnik ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
            'credit_limit' => number_format($creditLimit, 2, '.', ''),
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'amount' => '500.00',
            'paid_on' => '2026-09-11',
            'note' => null,
        ], $overrides);
    }

    #[Test]
    public function wplata_w_zlotowkach_nie_potrzebuje_kursu(): void
    {
        $order = $this->order();
        $register = $this->register();

        $result = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
        ]));

        $this->assertSame([], $result['errors']);

        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($result['id']);

        $this->assertSame('500.00', $payment->amount);
        $this->assertSame('PLN', $payment->currency);
        $this->assertSame('500.00', $payment->amount_base);
    }

    #[Test]
    public function wplata_w_euro_bez_kursu_jest_odrzucona(): void
    {
        $order = $this->order();
        $register = $this->register('EUR');

        // Kasa rozlicza sie w euro, a saldo zlecenia w zlotowkach. Bez
        // kursu nie da sie powiedziec, o ile ta wplata zmniejsza dlug —
        // a zgadniety kurs to zmyslona kwota w ksiegach.
        $result = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
        ]));

        $this->assertArrayHasKey('exchange_rate', $result['errors']);
        $this->assertNull($result['id']);
        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function kurs_zapisuje_sie_razem_z_przeliczona_kwota(): void
    {
        $order = $this->order();
        $register = $this->register('EUR');

        $result = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'amount' => '100.00',
            'exchange_rate' => '4.2750',
        ]));

        $this->assertSame([], $result['errors']);

        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($result['id']);

        // Kwota po przeliczeniu jest zapisana, nie liczona przy odczycie:
        // poprawiona jutro tabela kursow nie ma ruszyc historii.
        $this->assertSame('100.00', $payment->amount);
        $this->assertSame('EUR', $payment->currency);
        $this->assertSame('427.50', $payment->amount_base);
    }

    #[Test]
    public function waluta_bierze_sie_z_kasy_a_nie_z_formularza(): void
    {
        $order = $this->order();
        $register = $this->register('USD');

        $result = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'currency' => 'PLN',
            'exchange_rate' => '4.00',
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame('USD', Payment::query()->findOrFail($result['id'])->currency);
    }

    #[Test]
    public function wplata_na_zero_nic_nie_zmienia_wiec_jej_nie_ma(): void
    {
        $order = $this->order();
        $register = $this->register();

        $result = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'amount' => '0',
        ]));

        $this->assertArrayHasKey('amount', $result['errors']);
    }

    #[Test]
    public function korekta_jest_nowym_wierszem_a_oryginal_zostaje(): void
    {
        $order = $this->order();
        $register = $this->register();

        $first = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
        ]));

        $reversal = $this->service->reverse(
            (int) $order->getKey(),
            (int) $first['id'],
            'pomyłka kasjera',
        );

        $this->assertSame([], $reversal['errors']);
        $this->assertSame(2, Payment::query()->count());

        /** @var Payment $original */
        $original = Payment::query()->findOrFail($first['id']);
        /** @var Payment $storno */
        $storno = Payment::query()->findOrFail($reversal['id']);

        $this->assertSame('500.00', $original->amount);
        $this->assertSame('-500.00', $storno->amount);
        $this->assertSame((int) $original->getKey(), $storno->reversal_of_id);
    }

    #[Test]
    public function korekta_uzywa_kursu_z_wplaty_odwracanej(): void
    {
        $order = $this->order();
        $register = $this->register('EUR');

        $first = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'amount' => '100.00',
            'exchange_rate' => '4.2750',
        ]));

        $reversal = $this->service->reverse((int) $order->getKey(), (int) $first['id'], null);

        /** @var Payment $storno */
        $storno = Payment::query()->findOrFail($reversal['id']);

        // Korekta ma wyzerowac dokladnie te kwote, ktora weszla — kurs
        // z dnia korekty zostawilby w saldzie roznice kursowa znikad.
        $this->assertSame('-427.50', $storno->amount_base);
        $this->assertSame('4.275000', $storno->exchange_rate);
    }

    #[Test]
    public function korekty_nie_koryguje_sie_drugi_raz(): void
    {
        $order = $this->order();
        $register = $this->register();

        $first = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
        ]));

        $reversal = $this->service->reverse((int) $order->getKey(), (int) $first['id'], null);

        $again = $this->service->reverse((int) $order->getKey(), (int) $first['id'], null);
        $this->assertArrayHasKey('payment', $again['errors']);

        $ofReversal = $this->service->reverse((int) $order->getKey(), (int) $reversal['id'], null);
        $this->assertArrayHasKey('payment', $ofReversal['errors']);

        $this->assertSame(2, Payment::query()->count());
    }

    #[Test]
    public function cudzej_wplaty_nie_da_sie_skorygowac_z_innego_zlecenia(): void
    {
        $mine = $this->order();
        $other = $this->order();
        $register = $this->register();

        $payment = $this->service->store((int) $other->getKey(), $this->input([
            'cash_register_id' => $register->id,
        ]));

        $result = $this->service->reverse((int) $mine->getKey(), (int) $payment['id'], null);

        $this->assertArrayHasKey('payment', $result['errors']);
    }

    #[Test]
    public function saldo_liczy_sie_od_brutto_a_korekta_je_podnosi(): void
    {
        $order = $this->order();
        $register = $this->register();

        $payment = $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'amount' => '1230.00',
        ]));

        $board = $this->service->board((int) $order->getKey());

        $this->assertSame('1230.00', $board['summary']['gross']);
        $this->assertSame('1230.00', $board['summary']['paid']);
        $this->assertSame('0.00', $board['summary']['due']);
        $this->assertSame(100, $board['summary']['paid_percent']);

        $this->service->reverse((int) $order->getKey(), (int) $payment['id'], null);

        $after = $this->service->board((int) $order->getKey());

        $this->assertSame('0.00', $after['summary']['paid']);
        $this->assertSame('1230.00', $after['summary']['due']);
    }

    #[Test]
    public function bez_typu_faktury_saldo_jest_nieznane_a_nie_zerowe(): void
    {
        $order = $this->order(withInvoiceType: false);

        $board = $this->service->board((int) $order->getKey());

        // Netto znamy, brutto nie. „Do zaplaty 1000" byloby zdaniem
        // falszywym, bo do zaplaty jest kwota z VAT-em.
        $this->assertSame('1000.00', $board['summary']['net']);
        $this->assertNull($board['summary']['gross']);
        $this->assertNull($board['summary']['due']);
        $this->assertNull($board['summary']['paid_percent']);
    }

    #[Test]
    public function saldo_kontrahenta_sumuje_wszystkie_otwarte_zlecenia(): void
    {
        $contractor = $this->contractor(5000.0);

        $this->order(contractor: $contractor);
        $this->order(contractor: $contractor);

        // Dwa zlecenia po 1230 brutto to 2460 dlugu — pytanie brzmi
        // „czy ten klient ma jeszcze limit", nie „czy miesci sie to jedno".
        $this->assertSame('2460.00', (new ContractorBalance())->outstanding($contractor));
    }

    #[Test]
    public function nadplata_jednego_zlecenia_nie_zmniejsza_dlugu_z_innych(): void
    {
        $contractor = $this->contractor(5000.0);

        $paid = $this->order(contractor: $contractor);
        $this->order(contractor: $contractor);
        $register = $this->register();

        $this->service->store((int) $paid->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'amount' => '2000.00',
        ]));

        // Nadplata 770 zl na pierwszym zleceniu nie jest zaliczka na
        // drugie — ksiegowa rozlicza je osobno, wiec dlug to 1230.
        $this->assertSame('1230.00', (new ContractorBalance())->outstanding($contractor));
    }

    #[Test]
    public function zlecenie_w_statusie_koncowym_nie_jest_dlugiem(): void
    {
        $contractor = $this->contractor(5000.0);

        $order = $this->order(contractor: $contractor);

        /** @var Status $cancelled */
        $cancelled = Status::findByCode(StatusDomain::ORDER, 'ANULOWANE');
        $order->update(['status_id' => $cancelled->id]);

        $this->assertSame('0.00', (new ContractorBalance())->outstanding($contractor));
    }

    #[Test]
    public function karta_pokazuje_wplaty_zamiast_czekania_na_modul(): void
    {
        $order = $this->order();
        $register = $this->register();

        $this->service->store((int) $order->getKey(), $this->input([
            'cash_register_id' => $register->id,
            'amount' => '615.00',
        ]));

        $board = $this->service->board((int) $order->getKey());

        $this->assertSame(50, $board['summary']['paid_percent']);
        $this->assertSame(1, $board['tabs']['payments']);
    }
}
