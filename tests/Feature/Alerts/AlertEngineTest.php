<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\Contractor;
use App\Models\AlertRule;
use App\Enum\StatusDomain;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\AlertOccurrence;
use App\Services\Alerts\AlertBoard;
use App\Services\Alerts\AlertEngine;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Silnik alertów.
 *
 * Testowane jest **uzgadnianie**, nie samo zapalanie. Alert, który
 * potrafi się zapalić, ale nie gaśnie, jest gorszy od braku alertu:
 * po tygodniu ekran pokazuje listę spraw, z których połowa jest już
 * załatwiona, i przestaje się go czytać.
 */
class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    private AlertEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new AlertEngine();
    }

    #[Test]
    public function alert_zapala_sie_i_gasnie_bez_dopisywania_wierszy(): void
    {
        $order = $this->order(70001, '2026-03-01');
        $day = Carbon::parse('2026-03-10');

        $this->assertTrue($this->fires('order_overdue', $order, $day));
        $this->assertSame(1, $this->occurrences($order)->count());

        $order->client_deadline = Carbon::parse('2026-04-01');
        $order->save();

        $this->engine->forget();

        $this->assertFalse($this->fires('order_overdue', $order, $day));

        // Wiersz nie znika i nie dubluje sie — dostaje date zamkniecia.
        // Tabela ma niesc „od kiedy do kiedy", a nie dziennik przebiegow.
        $occurrences = $this->occurrences($order);
        $this->assertSame(1, $occurrences->count());
        $this->assertNotNull($occurrences->first()?->resolved_at);
    }

    #[Test]
    public function wartosc_w_bazie_zostaje_z_chwili_otwarcia(): void
    {
        $order = $this->order(70002, '2026-03-01');

        $this->fires('order_overdue', $order, Carbon::parse('2026-03-10'));

        $stored = $this->occurrences($order)->first()?->value;

        $this->engine->forget();
        $row = $this->row('order_overdue', $order, Carbon::parse('2026-03-20'));

        // Ekran pokazuje wartosc biezaca, baza pierwsza. Gdyby silnik
        // przepisywal te liczbe, kazdy odczyt listy bylby zapisem.
        $this->assertSame('9', $stored);
        $this->assertSame('19', $row['value'] ?? null);
        $this->assertSame('9', $this->occurrences($order)->first()?->value);
    }

    #[Test]
    public function zlecenie_bez_terminu_nie_jest_spoznione(): void
    {
        $order = $this->order(70003, null);

        // Brak terminu nie jest terminem zerowym — inaczej kazde nowe
        // zlecenie rodziloby sie spoznione.
        $this->assertFalse($this->fires('order_overdue', $order, Carbon::parse('2026-03-10')));
    }

    #[Test]
    public function zlecenie_w_statusie_koncowym_nie_alarmuje(): void
    {
        /** @var Status $final */
        $final = Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_final', true)
            ->firstOrFail();

        $order = $this->order(70004, '2026-03-01');
        $order->status_id = $final->id;
        $order->save();

        // Bez tego archiwum zalewa kazdy licznik i czerwona liczba przy
        // zakladce przestaje cokolwiek znaczyc.
        $this->assertFalse($this->fires('order_overdue', $order, Carbon::parse('2026-03-10')));
    }

    #[Test]
    public function wylaczenie_reguly_zamyka_otwarte_wystapienia(): void
    {
        $order = $this->order(70005, '2026-03-01');
        $day = Carbon::parse('2026-03-10');

        $this->fires('order_overdue', $order, $day);

        $rule = $this->rule('order_overdue');
        $this->engine->close($rule);

        $this->assertNotNull($this->occurrences($order)->first()?->resolved_at);
    }

    #[Test]
    public function regula_wskazujaca_nieznany_typ_wywala_sie(): void
    {
        $rule = $this->rule('order_overdue');
        $rule->condition = ['type' => 'wymyslony_warunek'];
        $rule->save();

        // Milczace pominiecie zostawiloby regule na ekranie jako aktywna,
        // a ona nie liczylaby nigdy niczego. Regula zepsuta ma krzyczec.
        $this->expectException(\RuntimeException::class);

        $this->engine->run(Carbon::parse('2026-03-10'));
    }

    #[Test]
    public function licznik_liczy_zlecenia_a_nie_alerty(): void
    {
        $order = $this->order(70006, '2026-03-01');
        $order->is_on_hold = true;
        $order->hold_reason = 'Klient zawiesił';
        $order->save();

        $day = Carbon::parse('2026-03-10');
        $board = new AlertBoard($this->engine);

        $alerts = $board->forOrders($day)[(int) $order->getKey()] ?? [];
        $counts = $board->orderCounts($day);

        // Zlecenie po terminie i wstrzymane to jedna sprawa do ruszenia,
        // nie dwie — licznik przy zakladce liczy zlecenia.
        $this->assertGreaterThanOrEqual(2, count($alerts));
        $this->assertSame(1, $counts['']);
    }

    #[Test]
    public function liczniki_zgadzaja_sie_ze_znacznikami_takze_dla_moich(): void
    {
        /** @var User $owner */
        $owner = User::query()->create([
            'first_name' => 'Anna',
            'last_name' => 'Liczniki',
            'email' => 'liczniki' . random_int(1000, 99999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        $mine = $this->order(70011, '2026-03-01');
        $mine->owner_id = (int) $owner->getKey();
        $mine->save();

        $this->order(70012, '2026-03-02');

        $day = Carbon::parse('2026-03-10');
        $board = new AlertBoard($this->engine);

        // Liczniki licza sie w bazie, znaczniki w silniku — to ma byc ten
        // sam zbior zlecen, inaczej czerwona liczba klamie o wierszach.
        $withMarks = array_keys(array_filter(
            $board->forOrders($day),
            static fn(array $marks): bool => array_filter(
                $marks,
                static fn(array $mark): bool => $mark['acknowledged'] !== true,
            ) !== [],
        ));

        $all = $board->orderCounts($day);
        $this->assertSame(count($withMarks), $all['']);
        $this->assertSame($all[''], $all['ZLECENIE']);

        $only = $board->orderCounts($day, (int) $owner->getKey());
        $this->assertSame(['' => 1, 'ZLECENIE' => 1], $only);
    }

    private function fires(string $code, Order $order, Carbon $day): bool
    {
        return $this->row($code, $order, $day) !== [];
    }

    /** @return array<string, mixed> */
    private function row(string $code, Order $order, Carbon $day): array
    {
        foreach ($this->engine->run($day) as $row) {
            if ($row['code'] === $code && (int) $row['alertable_id'] === (int) $order->getKey()) {
                return $row;
            }
        }

        return [];
    }

    private function rule(string $code): AlertRule
    {
        /** @var AlertRule */
        return AlertRule::query()->where('code', $code)->firstOrFail();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, AlertOccurrence> */
    private function occurrences(Order $order): \Illuminate\Database\Eloquent\Collection
    {
        return AlertOccurrence::query()
            ->where('alert_rule_id', $this->rule('order_overdue')->getKey())
            ->where('alertable_type', Order::class)
            ->where('alertable_id', $order->getKey())
            ->get();
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
