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
use App\Models\InvoiceType;
use App\Models\OrderDiscount;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Enum\InvestmentType;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderValue;
use App\Services\Orders\InvestmentService;
use App\Services\Orders\OrderListService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * VAT na zleceniu: stawka przy liście i proporcja z limitu powierzchni.
 *
 * Dwie rzeczy, które muszą być pewne, bo idą na fakturę:
 *
 * 1. **Suma się zgadza.** Netto zlecenia równa się sumie netto linii
 *    VAT co do grosza, także wtedy, gdy rabat sekcji trzeba rozdzielić
 *    na kilka list, a kwotę listy rozbić na dwie stawki.
 * 2. **Czego nie wiemy, tego nie zmyślamy.** Bez metrażu inwestycji
 *    proporcji nie da się policzyć — brutto zostaje `null`, a nie
 *    „całość na 8 %" ani „całość na 23 %".
 */
class OrderVatTest extends TestCase
{
    use RefreshDatabase;

    private OrderValue $value;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        $this->value = new OrderValue();
    }

    // ---------------------------------------------------------------
    // Stawka przy liście
    // ---------------------------------------------------------------

    #[Test]
    public function lista_bez_stawki_bierze_ja_z_typu_faktury(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->list($order, 1, ['szkło' => '1000.00']);

        $totals = $this->value->totals($this->fresh($order));

        $this->assertSame(23, $totals->vatRate);
        $this->assertSame('230.00', $totals->vat);
        $this->assertSame('1230.00', $totals->gross);
        $this->assertFalse($totals->mixedVat);
    }

    #[Test]
    public function stawka_zero_na_liscie_to_nie_to_samo_co_brak_stawki(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->list($order, 1, ['szkło' => '1000.00'], vatRate: 0);

        $totals = $this->value->totals($this->fresh($order));

        // Zero to prawdziwa stawka — brutto rowna sie netto, a nie
        // „nie wiemy".
        $this->assertSame(0, $totals->vatRate);
        $this->assertSame('0.00', $totals->vat);
        $this->assertSame('1000.00', $totals->gross);
        $this->assertSame('0.00', $totals->unknownNet);
    }

    #[Test]
    public function dwie_stawki_na_zleceniu_daja_brutto_bez_jednej_stawki(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->list($order, 1, ['szkło' => '1000.00']);
        $this->list($order, 2, ['usługi' => '500.00'], vatRate: 8);

        $totals = $this->value->totals($this->fresh($order));

        $this->assertTrue($totals->mixedVat);
        // Stawki nie ma, bo nie ma jednej stawki. Brutto jest, bo da
        // sie je policzyc — to dwa rozne pytania.
        $this->assertNull($totals->vatRate);
        $this->assertSame('270.00', $totals->vat);
        $this->assertSame('1770.00', $totals->gross);

        $this->assertSame(
            [['rate' => 23, 'net' => '1000.00'], ['rate' => 8, 'net' => '500.00']],
            array_map(
                static fn(array $line): array => ['rate' => $line['rate'], 'net' => $line['net']],
                $totals->vatLines,
            ),
        );
    }

    #[Test]
    public function bez_typu_faktury_i_bez_stawki_na_liscie_brutto_jest_nieznane(): void
    {
        $order = $this->order();
        $this->list($order, 1, ['szkło' => '1000.00']);

        $totals = $this->value->totals($this->fresh($order));

        $this->assertNull($totals->gross);
        $this->assertSame('1000.00', $totals->unknownNet);
        $this->assertNotNull($totals->unknownReason);
    }

    #[Test]
    public function stawka_spoza_slownika_typow_faktur_nie_przechodzi(): void
    {
        $order = $this->order();
        $this->list($order, 1, []);

        /** @var OrderList $list */
        $list = OrderList::query()->where('order_id', $order->getKey())->firstOrFail();

        $result = (new OrderListService())->save(
            (int) $order->getKey(),
            ['vat_rate' => 7],
            (int) $list->getKey(),
        );

        $this->assertArrayHasKey('vat_rate', $result['errors']);
    }

    // ---------------------------------------------------------------
    // Proporcja z limitu powierzchni
    // ---------------------------------------------------------------

    #[Test]
    public function inwestycja_w_limicie_zostaje_w_calosci_na_stawce_obnizonej(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->investment($order, InvestmentType::HOUSE, '250');
        $this->list($order, 1, ['usługi' => '1000.00'], vatRate: 8);

        $totals = $this->value->totals($this->fresh($order));

        $this->assertSame(8, $totals->vatRate);
        $this->assertSame('80.00', $totals->vat);
        $this->assertFalse($totals->mixedVat);
    }

    #[Test]
    public function dom_500_m2_dzieli_kwote_w_proporcji_300_do_500(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->investment($order, InvestmentType::HOUSE, '500');
        $this->list($order, 1, ['usługi' => '1000.00'], vatRate: 8);

        $totals = $this->value->totals($this->fresh($order));

        // 300/500 = 60 % → 600 zl na 8 %, 400 zl na 23 %.
        $this->assertSame(
            [
                ['rate' => 23, 'net' => '400.00', 'vat' => '92.00'],
                ['rate' => 8, 'net' => '600.00', 'vat' => '48.00'],
            ],
            array_map(
                static fn(array $line): array => [
                    'rate' => $line['rate'],
                    'net' => $line['net'],
                    'vat' => $line['vat'],
                ],
                $totals->vatLines,
            ),
        );
        $this->assertSame('140.00', $totals->vat);
        $this->assertSame('1140.00', $totals->gross);
    }

    #[Test]
    public function lokal_ma_wlasny_limit_150_m2(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->investment($order, InvestmentType::FLAT, '300');
        $this->list($order, 1, ['usługi' => '1000.00'], vatRate: 8);

        $totals = $this->value->totals($this->fresh($order));

        // 150/300 = 50 %. Ten sam metraz co w domu dalby 100 %.
        $this->assertSame('500.00', $totals->vatLines[1]['net']);
        $this->assertSame(8, $totals->vatLines[1]['rate']);
    }

    #[Test]
    public function proporcja_nie_dotyka_list_na_stawce_podstawowej(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->investment($order, InvestmentType::HOUSE, '500');
        $this->list($order, 1, ['szkło' => '1000.00']);
        $this->list($order, 2, ['usługi' => '1000.00'], vatRate: 8);

        $totals = $this->value->totals($this->fresh($order));

        // Szklo na 23 % nie ma czego dzielic: 1000 + 400 z podzialu.
        $this->assertSame('1400.00', $totals->vatLines[0]['net']);
        $this->assertSame(23, $totals->vatLines[0]['rate']);
        $this->assertSame('600.00', $totals->vatLines[1]['net']);
    }

    #[Test]
    public function bez_metrazu_inwestycji_brutto_jest_nieznane(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->investment($order, InvestmentType::HOUSE, null);
        $this->list($order, 1, ['usługi' => '1000.00'], vatRate: 8);

        $totals = $this->value->totals($this->fresh($order));

        // Ani 8 %, ani 23 % — nie wiemy, w jakiej proporcji. Zgadniecie
        // trafiloby na fakture i nikt by go nie zakwestionowal.
        $this->assertNull($totals->gross);
        $this->assertSame('1000.00', $totals->unknownNet);
        $this->assertNotNull($totals->unknownReason);
    }

    #[Test]
    public function metraz_bez_rodzaju_obiektu_nie_zostaje_zapisany(): void
    {
        $order = $this->order();

        (new InvestmentService())->save((int) $order->getKey(), [
            'investment_type' => null,
            'investment_area_m2' => '500',
        ]);

        $order->refresh();

        // Limit bierze sie z rodzaju obiektu. Metraz bez niego
        // wygladalby na dana, ktora na cos wplywa.
        $this->assertNull($order->investment_type);
        $this->assertNull($order->investment_area_m2);
    }

    // ---------------------------------------------------------------
    // Grosze
    // ---------------------------------------------------------------

    #[Test]
    public function suma_linii_vat_zgadza_sie_z_netto_zlecenia(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        $this->investment($order, InvestmentType::HOUSE, '437');
        $this->list($order, 1, ['usługi' => '333.33'], vatRate: 8);
        $this->list($order, 2, ['szkło' => '666.67']);

        $totals = $this->value->totals($this->fresh($order));

        $sum = 0.0;

        foreach ($totals->vatLines as $line) {
            $sum += (float) $line['net'];
        }

        $this->assertSame($totals->net, number_format($sum, 2, '.', ''));
    }

    #[Test]
    public function rabat_sekcji_rozdziela_sie_na_listy_bez_gubienia_grosza(): void
    {
        $order = $this->order();
        $this->invoiceType($order, 23);
        // Trzy listy w jednej sekcji i rabat, ktory nie dzieli sie
        // rowno — reszta musi trafic na ostatnia, a nie wyparowac.
        $this->list($order, 1, ['szkło' => '333.33']);
        $this->list($order, 2, ['szkło' => '333.33'], vatRate: 8);
        $this->list($order, 3, ['szkło' => '333.34']);

        // Rabat wpisany wprost, a nie przez serwis: test mowi o
        // rozdzieleniu groszy, nie o limitach rabatowych roli.
        OrderDiscount::query()->create([
            'order_id' => $order->getKey(),
            'section' => Section::GLASS->value,
            'percent' => '7.00',
        ]);

        $totals = $this->value->totals($this->fresh($order));

        $sum = 0.0;

        foreach ($totals->vatLines as $line) {
            $sum += (float) $line['net'];
        }

        $this->assertSame($totals->net, number_format($sum, 2, '.', ''));
    }

    // ---------------------------------------------------------------
    // Pomocnicze
    // ---------------------------------------------------------------

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'VAT ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]);
    }

    /** Typ faktury ze slownika — nie tworzymy wlasnego, bo wtedy test
     * potwierdzalby swoja fikcje zamiast konfiguracji systemu. */
    private function invoiceType(Order $order, int $rate): void
    {
        /** @var InvoiceType $type */
        $type = InvoiceType::query()->where('vat_rate', $rate)->firstOrFail();

        $order->update(['invoice_type_id' => $type->getKey()]);
    }

    private function investment(Order $order, InvestmentType $type, ?string $area): void
    {
        $order->update([
            'investment_type' => $type->value,
            'investment_area_m2' => $area,
        ]);
    }

    /**
     * Lista z pozycjami: nazwa sekcji → kwota.
     *
     * @param array<string, string> $items
     */
    private function list(Order $order, int $number, array $items, ?int $vatRate = null): OrderList
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => $number,
            'role' => 'component',
            'is_included' => true,
            'vat_rate' => $vatRate,
        ]);

        foreach ($items as $label => $amount) {
            OrderItem::query()->create([
                'order_list_id' => $list->id,
                'section' => $label === 'szkło' ? Section::GLASS->value : Section::SERVICES->value,
                'name' => $label,
                'quantity' => 1,
                'unit_net_price' => $amount,
                'amount' => $amount,
            ]);
        }

        return $list;
    }

    private function fresh(Order $order): Order
    {
        /** @var Order */
        return Order::query()
            ->with(['lists.items.processes', 'discounts', 'invoiceType'])
            ->findOrFail($order->getKey());
    }
}
