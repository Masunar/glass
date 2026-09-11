<?php

declare(strict_types=1);

namespace Tests\Feature\Production;

use Carbon\Carbon;
use Tests\TestCase;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\Workstation;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use App\Models\OrderItemProcess;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\RoleSeeder;
use Database\Seeders\Core\StatusSeeder;
use Database\Seeders\Core\ProcessSeeder;
use Database\Seeders\Core\LocationSeeder;
use App\Services\Production\ProductionPlan;
use App\Services\Production\ProductionQueue;
use App\Services\Production\TaskExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ewidencja etapów produkcji.
 *
 * Sedno: wykonanie jest osobne od marszruty wycenowej. Poprawka wymiaru
 * przelicza cenę i zakłada procesy od nowa — a praca, którą hala już
 * wykonała, ma to przetrwać.
 *
 * Drugie sedno: czas jest mierzony, nie zakładany. Etap odhaczony bez
 * rozpoczęcia nie dostaje czasu zgadywanego z niczego, bo z tych liczb
 * ma kiedyś powstać słownik czasów operacji.
 */
class ProductionTaskTest extends TestCase
{
    use RefreshDatabase;

    private ProductionPlan $plan;

    private TaskExecution $execution;

    private ProductionQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();
        (new ProcessSeeder())->run();

        Order::query()->delete();

        $this->plan = new ProductionPlan();
        $this->execution = new TaskExecution();
        $this->queue = new ProductionQueue();
    }

    private function contractor(): Contractor
    {
        /** @var Contractor */
        return Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Hala ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);
    }

    /**
     * @param list<string> $processCodes
     */
    private function order(
        array $processCodes = ['C', 'S'],
        string $statusCode = 'PRODUKCJA',
        bool $included = true,
        ?string $deadline = null,
    ): Order {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, $statusCode);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $this->contractor()->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'client_deadline' => $deadline,
        ]);

        /** @var OrderList $list */
        $list = OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => $included ? 'component' : 'alternative',
            'is_included' => $included,
        ]);

        /** @var OrderItem $item */
        $item = OrderItem::query()->create([
            'order_list_id' => $list->id,
            'section' => Section::GLASS->value,
            'name' => 'float 8mm',
            'quantity' => 1,
            'unit_net_price' => '500.00',
            'amount' => '500.00',
        ]);

        $this->attach($item, $processCodes);

        return $order->fresh(['lists.items.processes']) ?? $order;
    }

    /**
     * @param list<string> $codes
     */
    private function attach(OrderItem $item, array $codes): void
    {
        $position = 0;

        foreach ($codes as $code) {
            /** @var Process $process */
            $process = Process::findByCode($code);

            OrderItemProcess::query()->create([
                'order_item_id' => $item->id,
                'process_id' => $process->id,
                'unit_net_price' => '50.00',
                'amount' => '50.00',
                'position' => $position += 10,
            ]);
        }
    }

    private function firstTask(Order $order): ProductionTask
    {
        /** @var ProductionTask */
        return ProductionTask::query()
            ->where('order_id', $order->getKey())
            ->orderBy('position')
            ->firstOrFail();
    }

    #[Test]
    public function marszruta_staje_sie_zadaniami(): void
    {
        $order = $this->order(['C', 'S', 'H']);

        $this->plan->sync($order);

        $this->assertSame(3, ProductionTask::query()->where('order_id', $order->getKey())->count());
        $this->assertSame(ProductionStatus::PENDING, $this->firstTask($order)->status);
    }

    #[Test]
    public function powtorne_wejscie_na_produkcje_nie_zaklada_drugiego_kompletu(): void
    {
        $order = $this->order(['C', 'S']);

        $this->plan->sync($order);
        $this->plan->sync($order);

        // Zlecenie wraca na produkcje po poprawce. Drugi komplet zadan
        // znaczylby, ze hala ma zrobic wszystko dwa razy.
        $this->assertSame(2, ProductionTask::query()->where('order_id', $order->getKey())->count());
    }

    #[Test]
    public function alternatywa_nie_wchodzi_do_produkcji(): void
    {
        $order = $this->order(['C'], included: false);

        $this->plan->sync($order);

        // Lista oznaczona jako wariant nie jest tym, co klient zamowil.
        $this->assertSame(0, ProductionTask::query()->where('order_id', $order->getKey())->count());
    }

    #[Test]
    public function poprawka_wyceny_nie_kasuje_wykonanej_pracy(): void
    {
        $order = $this->order(['C', 'S']);
        $this->plan->sync($order);

        $cutting = $this->firstTask($order);
        $this->execution->finish((int) $cutting->getKey());

        // Ktos poprawia formatke: procesy sa kasowane i zakladane od
        // nowa, tym razem bez szlifu i bez ciecia.
        OrderItemProcess::query()->where('order_item_id', $cutting->order_item_id)->delete();
        $this->attach(OrderItem::query()->findOrFail($cutting->order_item_id), ['W']);

        $this->plan->sync($order->fresh(['lists.items.processes']) ?? $order);

        // Ciecie bylo zrobione naprawde — zostaje. Szlif, ktorego nikt
        // nie ruszyl, znika razem z marszruta.
        $this->assertDatabaseHas('production_tasks', [
            'id' => $cutting->getKey(),
            'status' => ProductionStatus::DONE->value,
        ]);
        $this->assertSame(2, ProductionTask::query()->where('order_id', $order->getKey())->count());
    }

    #[Test]
    public function zlecenie_z_niedokonczonym_etapem_nie_jest_gotowe(): void
    {
        $order = $this->order(['C', 'S']);
        $this->plan->sync($order);

        $this->assertFalse($this->plan->allDone($order));

        foreach (ProductionTask::query()->where('order_id', $order->getKey())->get() as $task) {
            $this->execution->finish((int) $task->getKey());
        }

        $this->assertTrue($this->plan->allDone($order));
    }

    #[Test]
    public function etap_zgloszony_jako_problem_blokuje_gotowe(): void
    {
        $order = $this->order(['C']);
        $this->plan->sync($order);

        $this->execution->reportIssue(
            (int) $this->firstTask($order)->getKey(),
            'breakage',
            'pekla przy cieciu',
        );

        // „Stoi" to nie „zrobione". Zlecenie z otwartym problemem nie ma
        // prawa przejsc dalej samo z siebie.
        $this->assertFalse($this->plan->allDone($order));
    }

    #[Test]
    public function problem_bez_opisu_jest_odrzucony(): void
    {
        $order = $this->order(['C']);
        $this->plan->sync($order);

        $id = (int) $this->firstTask($order)->getKey();

        // Czerwona kropka bez zdania to blokada, ktorej nikt nie umie
        // odblokowac.
        $this->assertArrayHasKey('note', $this->execution->reportIssue($id, 'breakage', '  ')['errors']);
        $this->assertArrayHasKey('issue_type', $this->execution->reportIssue($id, 'cokolwiek', 'opis')['errors']);
        $this->assertSame(ProductionStatus::PENDING, $this->firstTask($order)->status);
    }

    #[Test]
    public function czas_liczy_sie_tylko_od_rozpoczecia(): void
    {
        $order = $this->order(['C', 'S']);
        $this->plan->sync($order);

        $tasks = ProductionTask::query()->where('order_id', $order->getKey())->orderBy('position')->get();

        $measured = (int) $tasks[0]->getKey();
        $unmeasured = (int) $tasks[1]->getKey();

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:00'));
        $this->execution->start($measured);

        Carbon::setTestNow(Carbon::parse('2026-09-11 08:25'));
        $this->execution->finish($measured);
        $this->execution->finish($unmeasured);

        Carbon::setTestNow();

        // Zmierzone 25 minut i brak pomiaru tam, gdzie nikt nie
        // nacisnal „zacznij". Zero byloby liczba, ktora zatrulaby
        // slownik czasow operacji.
        $this->assertSame(25, ProductionTask::query()->findOrFail($measured)->minutes_spent);
        $this->assertNull(ProductionTask::query()->findOrFail($unmeasured)->minutes_spent);
    }

    #[Test]
    public function cofniecie_wykonania_kasuje_zmierzony_czas(): void
    {
        $order = $this->order(['C']);
        $this->plan->sync($order);

        $id = (int) $this->firstTask($order)->getKey();

        $this->execution->start($id);
        $this->execution->finish($id);
        $this->execution->reopen($id);

        /** @var ProductionTask $task */
        $task = ProductionTask::query()->findOrFail($id);

        // Pomiar przestal cokolwiek znaczyc, wiec przepada razem
        // z odhaczeniem — zamiast zostac w bazie jako smiec.
        $this->assertSame(ProductionStatus::PENDING, $task->status);
        $this->assertNull($task->minutes_spent);
        $this->assertNull($task->started_at);
    }

    #[Test]
    public function odhaczonego_etapu_nie_odhacza_sie_drugi_raz(): void
    {
        $order = $this->order(['C']);
        $this->plan->sync($order);

        $id = (int) $this->firstTask($order)->getKey();

        $this->execution->finish($id);

        $this->assertArrayHasKey('task', $this->execution->finish($id)['errors']);
    }

    #[Test]
    public function kolejka_ustawia_sie_wedlug_pilnosci_a_nie_numeru(): void
    {
        $today = Carbon::parse('2026-09-11');

        $later = $this->order(['C'], deadline: '2026-09-30');
        $urgent = $this->order(['C'], deadline: '2026-09-12');

        $this->plan->sync($later);
        $this->plan->sync($urgent);

        $board = $this->queue->board(today: $today);

        // Hala ma robic to, co sie pali, a nie to, co przyszlo pierwsze.
        $this->assertSame((int) $urgent->number, $board['rows'][0]['order_number']);
        $this->assertSame(1, $board['rows'][0]['days_left']);
    }

    #[Test]
    public function kolejka_pokazuje_parametr_etapu(): void
    {
        $order = $this->order(['K']);
        $this->plan->sync($order);

        ProductionTask::query()->where('order_id', $order->getKey())->update(['parameter' => 'RAL 9005']);

        $board = $this->queue->board();

        // Parametr jest tym, po co operator szedl dotad do biura.
        $this->assertSame('RAL 9005', $board['rows'][0]['parameter']);
    }

    #[Test]
    public function zrobione_znikaja_z_kolejki_dopoki_sie_ich_nie_pokaze(): void
    {
        $order = $this->order(['C']);
        $this->plan->sync($order);

        $this->execution->finish((int) $this->firstTask($order)->getKey());

        $this->assertSame(0, $this->queue->board()['summary']['shown']);
        $this->assertSame(1, $this->queue->board(includeDone: true)['summary']['shown']);
    }

    #[Test]
    public function stanowisko_filtruje_kolejke(): void
    {
        /** @var Workstation $station */
        $station = Workstation::query()->create([
            'name' => 'Szlifierka ' . random_int(100, 999),
            'is_active' => true,
            'position' => 1,
        ]);

        /** @var Process $grinding */
        $grinding = Process::findByCode('S');
        $grinding->update(['workstation_id' => $station->id]);

        $order = $this->order(['C', 'S']);
        $this->plan->sync($order);

        $mine = $this->queue->board(workstationId: (int) $station->getKey());
        $orphans = $this->queue->board(unassignedOnly: true);

        $this->assertSame(1, $mine['summary']['shown']);
        $this->assertSame('S', $mine['rows'][0]['process_code']);

        // Etapy bez stanowiska nie moga zniknac z ekranu: slownik
        // procesow ma te kolumne pusta tam, gdzie nikt jej nie
        // uzupelnil, i wlasnie te trzeba zobaczyc.
        $this->assertSame(1, $orphans['summary']['shown']);
        $this->assertSame('C', $orphans['rows'][0]['process_code']);
    }
}
