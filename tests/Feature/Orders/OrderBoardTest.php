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
use App\Enum\OrderPhase;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Lista zleceń dzieli wiersze na pasma pilności zamiast sortować po
 * jednej kolumnie. Pasmo jest tu jedyną rzeczą, którą łatwo pomylić,
 * więc każdy przypadek brzegowy ma własny test.
 */
class OrderBoardTest extends TestCase
{
    use RefreshDatabase;

    private OrderBoardService $board;
    private Carbon $today;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();

        $this->board = new OrderBoardService();
        $this->today = Carbon::parse('2026-09-03');
    }

    private function order(
        string $statusCode,
        ?Carbon $deadline,
        string $amount = '1000.00',
        array $attributes = [],
    ): Order {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, $statusCode);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Testowa ' . random_int(1000, 9999),
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'client_deadline' => $deadline,
            ...$attributes,
        ]);

        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => $attributes['list_included'] ?? true,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 6mm',
            'quantity' => 1,
            'unit_net_price' => $amount,
            'amount' => $amount,
        ]);

        return $order;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function bands(): array
    {
        $bands = [];

        /** @var list<array{key: string, rows: list<array<string, mixed>>, total: string, count: int}> $raw */
        $raw = $this->board->board(null, null, $this->today)['bands'];

        foreach ($raw as $band) {
            $bands[$band['key']] = $band;
        }

        return $bands;
    }

    #[Test]
    public function termin_na_dzis_trafia_do_pasma_dzis(): void
    {
        $this->order('ZLECENIE', $this->today->copy());

        $this->assertCount(1, $this->bands()['today']['rows']);
    }

    #[Test]
    public function termin_miniony_trafia_do_zaleglych(): void
    {
        $this->order('PRODUKCJA', $this->today->copy()->subDays(2));

        $rows = $this->bands()['overdue']['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame(-2, $rows[0]['days_left']);
    }

    #[Test]
    public function zlecenie_bez_terminu_nie_jest_zalegle(): void
    {
        // Brak terminu to brak decyzji do podjecia dzis, a nie
        // opoznienie — inaczej wyceny wstepne zalalyby pasmo alarmowe.
        $this->order('DO_WYCENY', null);

        $this->assertCount(0, $this->bands()['overdue']['rows']);
        $this->assertCount(1, $this->bands()['later']['rows']);
    }

    #[Test]
    public function status_koncowy_wygrywa_z_minionym_terminem(): void
    {
        // Archiwum z data sprzed miesiaca nie moze zalac zaleglych.
        $this->order('ARCHIWUM', $this->today->copy()->subDays(30));

        $this->assertCount(0, $this->bands()['overdue']['rows']);

        // Domyslnie lista pokazuje sprawy w toku — archiwum ma swoja
        // zakladke i tam stoi w pasmie „kolejne dni", nie w zaleglych.
        $this->assertCount(0, $this->bands()['later']['rows']);

        $archive = [];

        /** @var list<array{key: string, rows: list<array<string, mixed>>}> $raw */
        $raw = $this->board->board(null, 'ARCHIWUM', $this->today)['bands'];

        foreach ($raw as $band) {
            $archive[$band['key']] = $band['rows'];
        }

        $this->assertCount(0, $archive['overdue']);
        $this->assertCount(1, $archive['later']);
    }

    #[Test]
    public function najstarsze_zalegle_nie_wypadaja_przez_limit(): void
    {
        // Najstarszy termin, ale najnizszy numer — przy wyborze po
        // numerze wypadlby pierwszy. Symulacja na 10 000 zlecen:
        // najdluzej zalegle nie trafialy ani do pasma, ani na pulpit.
        $oldest = $this->order('ZLECENIE', $this->today->copy()->subDays(60), attributes: ['number' => 10001]);

        foreach (range(1, 3) as $offset) {
            $this->order('ZLECENIE', $this->today->copy()->addDays($offset), attributes: ['number' => 20000 + $offset]);
        }

        $board = $this->board->board(null, null, $this->today, limit: 2);
        $numbers = [];

        foreach ($board['bands'] as $band) {
            foreach ($band['rows'] as $row) {
                $numbers[] = $row['number'];
            }
        }

        $this->assertContains($oldest->number, $numbers);
    }

    #[Test]
    public function liczniki_pasm_licza_cala_baze_a_nie_wiersze(): void
    {
        foreach (range(1, 3) as $offset) {
            $this->order('ZLECENIE', $this->today->copy()->subDays($offset));
        }

        $board = $this->board->board(null, null, $this->today, limit: 1);

        // Jeden wiersz na ekranie, trzy zalegle w bazie. Pasek ma mowic
        // o bazie, a ekran — ze pokazuje jej wycinek.
        $this->assertSame(1, $board['summary']['shown']);
        $this->assertSame(3, $board['summary']['overdue']);
        $this->assertSame(3, $board['summary']['total']);
    }

    #[Test]
    public function termin_przesuniety_wygrywa_z_pierwotnym(): void
    {
        // W starym systemie prawdziwy termin siedzial w komentarzu,
        // a lista liczyla od innej daty i pokazywala opoznienie,
        // ktorego nie bylo.
        $this->order('PRODUKCJA', $this->today->copy()->subDays(5), attributes: [
            'shifted_deadline' => $this->today->copy(),
        ]);

        $this->assertCount(1, $this->bands()['today']['rows']);
        $this->assertCount(0, $this->bands()['overdue']['rows']);
    }

    #[Test]
    public function suma_pasma_liczy_wylacznie_listy_wliczone(): void
    {
        $order = $this->order('ZLECENIE', $this->today->copy(), '1000.00');

        /** @var OrderList $rejected */
        $rejected = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 2,
            'role' => 'alternative',
            'is_included' => false,
        ]);

        OrderItem::query()->create([
            'order_list_id' => $rejected->id,
            'section' => Section::GLASS->value,
            'name' => 'wariant droższy',
            'quantity' => 1,
            'unit_net_price' => '5000.00',
            'amount' => '5000.00',
        ]);

        $band = $this->bands()['today'];

        // Bez tego klient dostalby sume dwoch alternatyw.
        $this->assertSame('1000.00', $band['total']);
        $this->assertSame('1000.00', $band['rows'][0]['amount']);
    }

    #[Test]
    public function filtry_pomijaja_statusy_bez_zlecen(): void
    {
        $this->order('ZLECENIE', $this->today->copy());

        /** @var list<array{code: string|null, count: int}> $filters */
        $filters = $this->board->board(null, null, $this->today)['filters'];

        $codes = array_column($filters, 'code');

        // Stary system pokazywal kilkanascie pustych zakladek.
        $this->assertSame([null, 'ZLECENIE'], $codes);
        $this->assertSame(1, $filters[0]['count']);
    }

    #[Test]
    public function szukanie_po_numerze_dziala_na_cyfrach(): void
    {
        $order = $this->order('ZLECENIE', $this->today->copy());

        $found = $this->board->board((string) $order->number, null, $this->today);
        $rows = array_merge(...array_column($found['bands'], 'rows'));

        $this->assertCount(1, $rows);
        $this->assertSame($order->number, $rows[0]['number']);
    }

    #[Test]
    public function wiersz_niesie_dalszy_krok_albo_powod_blokady(): void
    {
        $this->order('ZLECENIE', $this->today->copy());

        $row = $this->bands()['today']['rows'][0];

        // Wiersz bez zadnej informacji o dalszym kroku jest gorszy niz
        // wiersz mowiacy, czego brakuje.
        $this->assertTrue(
            $row['next_step'] !== null || $row['blocked_step'] !== null,
            'Wiersz nie mówi, co dalej.',
        );
    }

    #[Test]
    public function faza_pokazuje_wszystkie_swoje_statusy(): void
    {
        $this->order('ZLECENIE', $this->today->copy()->addDays(3));
        $this->order('PRODUKCJA', $this->today->copy()->addDays(3));
        $this->order('DO_WYCENY', $this->today->copy()->addDays(3));

        $board = $this->board->board(null, null, $this->today, phase: 'production');

        // „Realizacja" to zlecenie, produkcja i gotowe razem — pierwsze
        // klikniecie w faze ma pokazac cala faze, a nie tylko menu.
        $this->assertSame(2, $board['summary']['total']);
    }

    #[Test]
    public function kazdy_zaseedowany_status_ma_swoja_faze(): void
    {
        $statuses = Status::query()->where('domain', StatusDomain::ORDER->value)->get();

        $this->assertNotEmpty($statuses);

        // „Inne" jest siatka bezpieczenstwa dla statusu dodanego pozniej
        // w slowniku, nie miejscem dla statusow z katalogu. Status
        // z katalogu w „Innych" znaczy, ze mapa faz zostala w tyle.
        foreach ($statuses as $status) {
            $this->assertNotSame(
                OrderPhase::OTHER,
                OrderPhase::forStatus((string) $status->code, (bool) $status->is_final),
                (string) $status->code,
            );
        }
    }

    #[Test]
    public function alerty_osobno_to_te_same_alerty_co_w_jednym_kawalku(): void
    {
        $late = $this->order('ZLECENIE', $this->today->copy()->subDays(5));
        $this->order('ZLECENIE', $this->today->copy()->addDays(5));

        $whole = (new OrderBoardService())->board(today: $this->today);
        $rows = (new OrderBoardService())->board(today: $this->today, withAlerts: false);

        $expected = [];
        $ids = [];

        foreach ($whole['bands'] as $band) {
            foreach ($band['rows'] as $row) {
                $expected[$row['id']] = $row['alerts'];
            }
        }

        foreach ($rows['bands'] as $band) {
            foreach ($band['rows'] as $row) {
                $ids[] = $row['id'];
                $this->assertSame([], $row['alerts']);
            }
        }

        $alerts = (new OrderBoardService())->alerts($ids, $this->today);

        // Bez alertow ekran ma wiedziec, ze ich jeszcze nie ma — zero
        // przy zakladce znaczyloby „wszystko w porzadku".
        $this->assertFalse($rows['summary']['alerts_loaded']);
        $this->assertNull($rows['filters'][0]['alerts']);

        $this->assertNotSame([], $expected[(int) $late->getKey()]);
        $this->assertEquals($expected, $alerts['marks']);
        $this->assertSame($whole['filters'][0]['alerts'], $alerts['counts']['']);
    }
}
