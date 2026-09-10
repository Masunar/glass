<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\OrderPane;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\InvoiceType;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\OrderItemProcess;
use App\Services\Orders\OrderCard;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\ProcessSeeder;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\DictionarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Karta zlecenia odpowiada na cztery pytania: ile to warte, na kiedy,
 * czy klient mieści się w limicie i co można z tym zrobić. Testy
 * pilnują przede wszystkim tego, czego karta **nie** ma prawa
 * powiedzieć: brutto bez znanej stawki VAT i kwoty z odrzuconych
 * wariantów.
 */
class OrderCardTest extends TestCase
{
    use RefreshDatabase;

    private OrderCard $card;
    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();
        (new ProcessSeeder())->run();
        (new DictionarySeeder())->run();

        Order::query()->delete();

        $this->card = new OrderCard();
        $this->today = Carbon::parse('2026-09-03');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function order(array $attributes = []): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Karta ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
            'credit_limit' => '2500.00',
            'payment_days' => 14,
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            ...$attributes,
        ]);
    }

    private function list(Order $order, bool $included, string $amount): OrderItem
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => $included ? 1 : 2,
            'role' => $included ? 'component' : 'alternative',
            'is_included' => $included,
        ]);

        /** @var OrderItem */
        return OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 6mm',
            'quantity' => 1,
            'unit_net_price' => $amount,
            'amount' => $amount,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardFor(Order $order): array
    {
        return $this->card->card((int) $order->getKey(), $this->today);
    }

    #[Test]
    public function odrzucony_wariant_nie_wchodzi_do_kwoty(): void
    {
        $order = $this->order();
        $this->list($order, true, '1000.00');
        $this->list($order, false, '640.00');

        $money = $this->cardFor($order)['money'];

        $this->assertSame('1000.00', $money['net']);
        $this->assertSame('640.00', $money['excluded_net']);
    }

    #[Test]
    public function bez_typu_faktury_karta_nie_pokazuje_brutto(): void
    {
        $order = $this->order();
        $this->list($order, true, '1000.00');

        $money = $this->cardFor($order)['money'];

        $this->assertNull($money['vat_rate']);
        $this->assertNull($money['vat']);
        $this->assertNull($money['gross']);
    }

    #[Test]
    public function z_typem_faktury_brutto_liczy_sie_z_jego_stawki(): void
    {
        /** @var InvoiceType $type */
        $type = InvoiceType::query()->where('vat_rate', 8)->firstOrFail();

        $order = $this->order(['invoice_type_id' => $type->id]);
        $this->list($order, true, '1000.00');

        $money = $this->cardFor($order)['money'];

        $this->assertSame(8, $money['vat_rate']);
        $this->assertSame('80.00', $money['vat']);
        $this->assertSame('1080.00', $money['gross']);
    }

    #[Test]
    public function przekroczony_limit_kupiecki_jest_wyliczony_od_wartosci_zlecenia(): void
    {
        $order = $this->order();
        $this->list($order, true, '3000.00');

        $credit = $this->cardFor($order)['credit'];

        $this->assertSame('2500.00', $credit['limit']);
        $this->assertSame('500.00', $credit['exceeds_by']);
        $this->assertFalse($credit['is_gross']);
    }

    #[Test]
    public function limit_w_granicach_nie_zglasza_przekroczenia(): void
    {
        $order = $this->order();
        $this->list($order, true, '900.00');

        $this->assertNull($this->cardFor($order)['credit']['exceeds_by']);
    }

    #[Test]
    public function sciezka_produkcji_bierze_procesy_z_pozycji_w_kolejnosci_slownika(): void
    {
        $order = $this->order();
        $item = $this->list($order, true, '1000.00');

        OrderPane::query()->create([
            'order_item_id' => $item->id,
            'width_mm' => 1000,
            'height_mm' => 500,
            'is_tempered' => true,
        ]);

        // Wstawione odwrotnie do kolejnosci slownikowej.
        foreach (['H', 'C'] as $code) {
            /** @var Process $process */
            $process = Process::findByCode($code);

            OrderItemProcess::query()->create([
                'order_item_id' => $item->id,
                'process_id' => $process->id,
                'unit_net_price' => '10.00',
                'amount' => '30.00',
            ]);
        }

        $path = $this->cardFor($order)['path'];

        $this->assertSame(['C', 'H'], array_column($path, 'code'));
        $this->assertSame(1, $path[0]['items']);
    }

    #[Test]
    public function procesy_wchodza_do_kwoty_pozycji(): void
    {
        $order = $this->order();
        $item = $this->list($order, true, '1000.00');

        /** @var Process $process */
        $process = Process::findByCode('C');

        OrderItemProcess::query()->create([
            'order_item_id' => $item->id,
            'process_id' => $process->id,
            'unit_net_price' => '10.00',
            'amount' => '120.00',
        ]);

        $card = $this->cardFor($order);

        $this->assertSame('1120.00', $card['money']['net']);
        $this->assertSame('1120.00', $card['lists'][0]['items'][0]['amount']);
    }

    #[Test]
    public function termin_liczy_sie_od_przesunietego_gdy_taki_jest(): void
    {
        $order = $this->order([
            'client_deadline' => $this->today->copy()->subDays(5),
            'shifted_deadline' => $this->today->copy()->addDays(3),
        ]);
        $this->list($order, true, '100.00');

        $deadline = $this->cardFor($order)['order']['deadline'];

        $this->assertSame(3, $deadline['days_left']);
    }

    #[Test]
    public function wylaczona_lista_zostaje_w_karcie_i_jest_oznaczona(): void
    {
        $order = $this->order();
        $this->list($order, true, '1000.00');
        $this->list($order, false, '640.00');

        $lists = $this->cardFor($order)['lists'];

        $this->assertCount(2, $lists);
        $this->assertFalse($lists[1]['is_included']);
        $this->assertSame('alternative', $lists[1]['role']);
    }

    #[Test]
    public function karta_niesie_wszystkie_przejscia_a_nie_tylko_dostepne(): void
    {
        $order = $this->order();
        $this->list($order, true, '1000.00');

        $steps = $this->cardFor($order)['steps'];

        // ZLECENIE ma dwa przejscia: produkcja i anulowanie.
        $this->assertCount(2, $steps);
        $this->assertContains('ANULOWANE', array_column($steps, 'to_status_code'));
    }
}
