<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\AlertRule;
use App\Models\Contractor;
use App\Enum\StatusDomain;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\AlertOccurrence;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertEngine;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use App\Services\Alerts\AlertAcknowledgement;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Odhaczanie alertów — „wiem o tym".
 *
 * Pilnowane są dwie granice. Pierwsza: **odhaczenie to nie zamknięcie**
 * — wystąpienie zostaje otwarte, bo warunek nadal trwa, a zlecenie po
 * terminie po odhaczeniu dalej jest po terminie.
 *
 * Druga: **odhaczenie nie jest wieczne**. Alert odhaczony przy jednym
 * dniu spóźnienia musi wrócić przy dwóch, inaczej odhaczenie kasuje
 * sprawę zamiast ją uciszać — a to jest gorsze niż brak odhaczania,
 * bo znika bez śladu i nikt tego nie zauważy.
 */
class AlertAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private AlertEngine $engine;

    private AlertAcknowledgement $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new AlertEngine();
        $this->service = new AlertAcknowledgement($this->engine);

        // Odhaczenie sprawdza uprawnienia w usludze, a nie w kontrolerze
        // (wymaganie zalezy od wiersza, nie od trasy), wiec wolanie jej
        // wprost tez musi miec kim byc. Wczesniej sprawdzenie stalo
        // w `protect()` i testy uslugi go nie widzialy.
        $this->actingAs($this->userWith([
            'zlec.access', 'orders.update',
            'mag.access', 'warehouse.update',
            'prod.access', 'tempering.update',
        ]));
    }

    #[Test]
    public function odhaczenie_nie_zamyka_wystapienia(): void
    {
        $order = $this->order(80001, '2026-03-01');
        $occurrence = $this->fire($order, Carbon::parse('2026-03-10'));

        $this->assertSame(
            [],
            $this->service->acknowledge($occurrence, Carbon::parse('2026-03-10'))['errors'],
        );

        /** @var AlertOccurrence $row */
        $row = AlertOccurrence::query()->findOrFail($occurrence);

        // `resolved_at` znaczy „warunek przestal byc spelniony". Warunek
        // trwa, wiec wystapienie zostaje otwarte — zmienia sie tylko to,
        // czy krzyczy.
        $this->assertNull($row->resolved_at);
        $this->assertNotNull($row->acknowledged_at);
        $this->assertSame('9', $row->acknowledged_value);
    }

    #[Test]
    public function odhaczony_znika_z_licznika_ale_zostaje_w_wierszu(): void
    {
        $order = $this->order(80002, '2026-03-01');
        $day = Carbon::parse('2026-03-10');
        $occurrence = $this->fire($order, $day);

        $this->service->acknowledge($occurrence, $day);
        $this->engine->forget();

        $board = new AlertBoard($this->engine);
        $marks = $board->forOrders($day)[(int) $order->getKey()] ?? [];

        // Licznik odpowiada na „ile wymaga reakcji", znacznik na „co
        // jest z tym zleceniem". Dwa pytania, dwie odpowiedzi.
        $this->assertSame(0, $board->orderCounts($day)['']);
        $this->assertNotSame([], $marks);
        $this->assertTrue($marks[0]['acknowledged']);
    }

    #[Test]
    public function alert_wraca_gdy_zrobi_sie_gorzej(): void
    {
        $order = $this->order(80003, '2026-03-01');
        $occurrence = $this->fire($order, Carbon::parse('2026-03-10'));

        $this->service->acknowledge($occurrence, Carbon::parse('2026-03-10'));
        $this->engine->forget();

        // Ten sam warunek, wiekszy poslizg. Odhaczenie z dziewieciu dni
        // nie obejmuje dziewietnastu.
        $rows = $this->engine->run(Carbon::parse('2026-03-20'));
        $row = $this->rowFor($rows, $occurrence);

        $this->assertFalse($row['acknowledged']);
        $this->assertNull(AlertOccurrence::query()->findOrFail($occurrence)->acknowledged_at);
    }

    #[Test]
    public function alert_nie_wraca_gdy_nic_sie_nie_zmienilo(): void
    {
        $order = $this->order(80004, '2026-03-01');
        $day = Carbon::parse('2026-03-10');
        $occurrence = $this->fire($order, $day);

        $this->service->acknowledge($occurrence, $day);
        $this->engine->forget();

        $row = $this->rowFor($this->engine->run($day), $occurrence);

        $this->assertTrue($row['acknowledged']);
    }

    #[Test]
    public function warunek_bez_liczby_zostaje_odhaczony(): void
    {
        $order = $this->order(80005, null);
        $order->is_on_hold = true;
        $order->hold_reason = 'Klient zawiesił';
        $order->save();

        $day = Carbon::parse('2026-03-10');
        $occurrence = $this->fire($order, $day, 'order_on_hold');

        $this->service->acknowledge($occurrence, $day);
        $this->engine->forget();

        // „Wstrzymane" nie ma czego pogorszyc. Brak liczby nie jest
        // pogorszeniem, a zgadywanie, ze „cos sie zmienilo", byloby
        // wymyslaniem wartosci, ktorej warunek nigdy nie zwrocil.
        $row = $this->rowFor($this->engine->run(Carbon::parse('2026-06-01')), $occurrence);

        $this->assertTrue($row['acknowledged']);
    }

    #[Test]
    public function cofniecie_przywraca_alert(): void
    {
        $order = $this->order(80006, '2026-03-01');
        $day = Carbon::parse('2026-03-10');
        $occurrence = $this->fire($order, $day);

        $this->service->acknowledge($occurrence, $day);
        $this->assertSame([], $this->service->revoke($occurrence)['errors']);
        $this->engine->forget();

        $board = new AlertBoard($this->engine);

        $this->assertSame(1, $board->orderCounts($day)['']);
        $this->assertNull(AlertOccurrence::query()->findOrFail($occurrence)->acknowledged_value);
    }

    #[Test]
    public function odhaczenie_wymaga_prawa_do_zmiany_zlecenia_a_nie_do_regul(): void
    {
        $order = $this->order(80007, '2026-03-01');
        $occurrence = $this->fire($order, Carbon::parse('2026-03-10'));

        // Konfiguracja regul to `alerts`; odhaczenie to decyzja
        // o zleceniu. Handlowiec bez dostepu do panelu admina musi
        // moc powiedziec „wiem o tym".
        $this->actingAs($this->userWith(['adm.access', 'alerts.update']))
            ->postJson('/api/alert-occurrences/' . $occurrence . '/acknowledge')
            ->assertForbidden();

        // Samo `orders.update` bez dostepu do modulu tez nie wystarcza:
        // sprawdzenie pyta o oba poziomy (U-04), mimo ze nie robi tego
        // posrednik — trasa nie ma z czego wyprowadzic uprawnienia.
        $this->actingAs($this->userWith(['orders.update']))
            ->postJson('/api/alert-occurrences/' . $occurrence . '/acknowledge')
            ->assertForbidden();

        $this->actingAs($this->userWith(['zlec.access', 'orders.update']))
            ->postJson('/api/alert-occurrences/' . $occurrence . '/acknowledge')
            ->assertOk();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function rowFor(array $rows, int $occurrence): array
    {
        foreach ($rows as $row) {
            if ((int) $row['occurrence_id'] === $occurrence) {
                return $row;
            }
        }

        $this->fail('Wystąpienie ' . $occurrence . ' zniknęło z przebiegu.');
    }

    private function fire(Order $order, Carbon $day, string $code = 'order_overdue'): int
    {
        $this->engine->forget();
        $this->engine->run($day);

        /** @var AlertRule $rule */
        $rule = AlertRule::query()->where('code', $code)->firstOrFail();

        /** @var AlertOccurrence $occurrence */
        $occurrence = AlertOccurrence::query()
            ->where('alert_rule_id', $rule->getKey())
            ->where('alertable_id', $order->getKey())
            ->whereNull('resolved_at')
            ->firstOrFail();

        return (int) $occurrence->getKey();
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions): User
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola odhaczania ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Odhaczanie',
            'email' => 'ack' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh() ?? $user;
    }

    private function order(int $number, ?string $deadline): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $party */
        $party = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Klient ' . $number,
            'tax_id' => '8522347066',
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => $number,
            'contractor_id' => $party->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'client_deadline' => $deadline,
            // Komplet rysunkow, zeby regula „brak rysunkow" nie mieszala
            // sie do testow o terminie.
            'drawings_complete_at' => Carbon::parse('2026-02-01'),
        ]);
    }
}
