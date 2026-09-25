<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\Contractor;
use App\Enum\StatusDomain;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use Illuminate\Support\Facades\DB;
use App\Services\Alerts\AlertEngine;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Trzy reguły z 25.09: za długo w statusie, brak wyceny, ponad limitem.
 */
class AlertNewRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
    }

    private function order(string $statusCode, string $creditLimit = '0.00'): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, $statusCode);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Reguły ' . random_int(1000, 99999),
            'tax_id' => '8522347066',
            'credit_limit' => $creditLimit,
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            // Rysunki potwierdzone, zeby „brak rysunkow" nie mieszal sie
            // do tych testow.
            'drawings_complete_at' => Carbon::now(),
        ]);
    }

    private function item(Order $order, ?string $price, bool $included = true): void
    {
        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => (int) OrderList::query()->where('order_id', $order->id)->count() + 1,
            'role' => $included ? 'component' : 'alternative',
            'is_included' => $included,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 6mm',
            'quantity' => 1,
            'unit_net_price' => $price,
            'amount' => $price ?? '0.00',
        ]);
    }

    /**
     * @return array<int, string|null> id zlecenia => wartość alertu
     */
    private function fired(string $code, ?Carbon $day = null): array
    {
        $found = [];

        foreach ((new AlertEngine())->run($day ?? Carbon::today()) as $row) {
            if ($row['code'] === $code) {
                $found[(int) $row['alertable_id']] = $row['value'];
            }
        }

        return $found;
    }

    #[Test]
    public function zmiana_statusu_ustawia_date_w_statusie(): void
    {
        $order = $this->order('DO_WYCENY');
        DB::table('orders')->where('id', $order->id)->update(['status_changed_at' => '2026-01-01 10:00:00']);

        /** @var Status $next */
        $next = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');
        $fresh = Order::query()->findOrFail($order->id);
        $fresh->status_id = $next->id;
        $fresh->save();

        // Data w modelu, nie w przejsciu — kazda droga zmiany statusu
        // (przejscie, symulacja, zasiew) ja przestawia.
        $this->assertTrue(Order::query()->findOrFail($order->id)->status_changed_at?->isToday());
    }

    #[Test]
    public function zlecenie_stojace_ponad_prog_zapala_sie_z_liczba_dni(): void
    {
        $stuck = $this->order('ZLECENIE');
        $fresh = $this->order('ZLECENIE');

        DB::table('orders')->where('id', $stuck->id)
            ->update(['status_changed_at' => Carbon::today()->subDays(10)->setTime(9, 0)]);
        DB::table('orders')->where('id', $fresh->id)
            ->update(['status_changed_at' => Carbon::today()->subDays(3)->setTime(9, 0)]);

        $fired = $this->fired('order_stuck');

        $this->assertSame('10', $fired[(int) $stuck->id] ?? null);
        $this->assertArrayNotHasKey((int) $fresh->id, $fired);
    }

    #[Test]
    public function brak_wyceny_lapie_pusta_wycene_i_pozycje_bez_ceny(): void
    {
        $empty = $this->order('DO_WYCENY');

        $unpriced = $this->order('DO_WYCENY');
        $this->item($unpriced, '120.00');
        $this->item($unpriced, null);

        $priced = $this->order('DO_WYCENY');
        $this->item($priced, '120.00');
        // Wariant odrzucony nie idzie na oferte — jego brak ceny nie
        // jest brakiem wyceny zlecenia.
        $this->item($priced, null, included: false);

        $fired = $this->fired('order_unpriced');

        $this->assertArrayHasKey((int) $empty->id, $fired);
        $this->assertNull($fired[(int) $empty->id]);
        $this->assertSame('1', $fired[(int) $unpriced->id] ?? null);
        $this->assertArrayNotHasKey((int) $priced->id, $fired);
    }

    #[Test]
    public function ponad_limitem_zapala_sie_z_kwota_przekroczenia(): void
    {
        $over = $this->order('ZLECENIE', creditLimit: '100.00');
        $this->item($over, '500.00');

        $within = $this->order('ZLECENIE', creditLimit: '10000.00');
        $this->item($within, '500.00');

        $fired = $this->fired('order_over_credit_limit');

        // Bez typu faktury dlug liczy sie od netto — ta sama regula co
        // na karcie. 500 dlugu przy limicie 100 to 400 ponad.
        $this->assertSame('400.00', $fired[(int) $over->id] ?? null);
        $this->assertArrayNotHasKey((int) $within->id, $fired);
    }
}
