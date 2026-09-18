<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Unit;
use App\Enum\Section;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderList;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\PriceSection;
use App\Models\ProductGroup;
use App\Models\ProductService;
use App\Models\PurchasePrice;
use App\Models\ProductionTask;
use App\Models\OrderItemProcess;
use App\Enum\ProductionStatus;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use Database\Seeders\Core\RoleSeeder;
use PHPUnit\Framework\Attributes\Test;
use Database\Seeders\Core\StatusSeeder;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderSchedule;
use App\Services\Production\ProductionPlan;
use App\Services\Production\TaskExecution;
use Database\Seeders\Core\ProcessSeeder;
use Database\Seeders\Core\LocationSeeder;
use Database\Seeders\Core\GlassCatalogSeeder;
use Database\Seeders\Core\PriceSectionSeeder;
use Database\Seeders\Core\GlobalParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wybór pozycji cennikowej procesu i czas etapu.
 *
 * Sedno: **grubość szkła zawęża listę, ale jej nie rozstrzyga.**
 * Fazowanie ma dla ośmiomilimetrowej szyby osiem wierszy — faza od 5 do
 * 40 mm, od 5 do 22,50 zł. Automat biorący pierwszy z brzegu wstawiłby
 * cichaczem fazę 5 mm tam, gdzie klient zamówił 30 mm, a kwota by się
 * pojawiła i nikt by nie zauważył. Dlatego przy kilku kandydatach
 * pozycja czeka na człowieka i jest oznaczona jako niewyceniona.
 *
 * Drugie sedno: dni etapu biorą się ze słownika procesów i sumują na
 * formatce — to jedyna liczba dni, jaką wolno tu podać.
 */
class OrderProcessChoiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder())->run();
        (new LocationSeeder())->run();
        (new StatusSeeder())->run();
        (new ProcessSeeder())->run();
        (new GlassCatalogSeeder())->run();
        (new PriceSectionSeeder())->run();
        (new GlobalParameterSeeder())->run();

        Order::query()->delete();

        $this->service = new OrderItemService();
    }

    private function glass(): Product
    {
        /** @var Product */
        return Product::query()->where('name', 'float 8mm')->firstOrFail();
    }

    private function section(string $section = Section::GLASS->value): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', $section)
            ->where('name', 'Detaliczny podstawowy')
            ->firstOrFail();
    }

    private function group(): ProductGroup
    {
        /** @var ProductGroup */
        return ProductGroup::query()->firstOrCreate(
            ['section' => Section::SERVICES->value, 'name' => 'Obróbka krawędzi'],
            ['position' => 10],
        );
    }

    /** Pozycja cennikowa procesu: nazwa, grubość zawężająca, cena. */
    private function position(
        string $processCode,
        string $name,
        ?float $thickness,
        string $purchaseNet,
    ): Product {
        /** @var Process $process */
        $process = Process::findByCode($processCode);

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $this->group()->id,
            'section' => Section::SERVICES->value,
            'name' => $name,
            'unit' => Unit::RUNNING_METER->value,
            'vat_rate' => 23,
        ]);

        ProductService::query()->create([
            'product_id' => $product->id,
            'process_id' => $process->id,
            'glass_thickness_mm' => $thickness,
        ]);

        PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $purchaseNet,
            'source' => PurchasePriceSource::MANUAL->value,
            'valid_from' => now()->subDay(),
        ]);

        (new PriceListService())->update([[
            'product_id' => $product->id,
            'price_section_id' => $this->section(Section::SERVICES->value)->id,
            'coefficient' => '1.0',
        ]]);

        return $product;
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'DO_WYCENY');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Wybór ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
        ]);

        OrderList::query()->create([
            'order_id' => $order->id,
            'number' => 1,
            'role' => 'component',
            'is_included' => true,
        ]);

        return $order;
    }

    /**
     * @param list<array<string, mixed>>|list<int> $processes
     * @return array<string, mixed>
     */
    private function pane(array $processes = []): array
    {
        return [
            'product_id' => $this->glass()->id,
            'width_mm' => 1000,
            'height_mm' => 1000,
            'quantity' => 1,
            'processes' => $processes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function firstProcessRow(int $orderId): array
    {
        $board = $this->service->board($orderId);
        /** @var array<string, mixed> $row */
        $row = $board['lists'][0]['glass'][0]['processes'][0];

        return $row;
    }

    #[Test]
    public function jeden_kandydat_wybiera_sie_sam(): void
    {
        $order = $this->order();
        $cutting = $this->position('C', 'Cięcie 8mm', 8.0, '0.75');

        /** @var Process $process */
        $process = Process::findByCode('C');

        $result = $this->service->savePane(
            (int) $order->getKey(),
            $this->pane([['process_id' => $process->id]]),
        );

        $this->assertSame([], $result['errors']);

        $row = $this->firstProcessRow((int) $order->getKey());

        // Ciecie ma dla kazdej grubosci jeden wiersz, wiec nie ma o co
        // pytac — automat moze wybrac.
        $this->assertSame((int) $cutting->getKey(), $row['product_id']);
        $this->assertSame('Cięcie 8mm', $row['parameter']);
        $this->assertSame('0.75', $row['unit_net_price']);
    }

    #[Test]
    public function kilku_kandydatow_czeka_na_czlowieka(): void
    {
        $order = $this->order();

        // Trzy fazy dla tej samej osmiomilimetrowej szyby, po roznych
        // cenach. Roznica miedzy nimi to nie grubosc szkla, tylko
        // szerokosc fazy — czego zaden automat nie zgadnie.
        $this->position('F', 'Faza 5mm', 8.0, '5.00');
        $this->position('F', 'Faza 15mm', 8.0, '8.00');
        $this->position('F', 'Faza 30mm', 8.0, '17.00');

        /** @var Process $bevel */
        $bevel = Process::findByCode('F');

        $this->service->savePane(
            (int) $order->getKey(),
            $this->pane([['process_id' => $bevel->id]]),
        );

        $row = $this->firstProcessRow((int) $order->getKey());

        $this->assertNull($row['product_id']);
        $this->assertSame('0.00', $row['amount']);

        /** @var OrderItemProcess $entry */
        $entry = OrderItemProcess::query()->firstOrFail();
        $this->assertNull($entry->parameter);
    }

    #[Test]
    public function wskazana_pozycja_decyduje_o_cenie_i_parametrze(): void
    {
        $order = $this->order();

        $this->position('F', 'Faza 5mm', 8.0, '5.00');
        $wide = $this->position('F', 'Faza 30mm', 8.0, '17.00');

        /** @var Process $bevel */
        $bevel = Process::findByCode('F');

        $this->service->savePane((int) $order->getKey(), $this->pane([[
            'process_id' => $bevel->id,
            'product_id' => $wide->id,
        ]]));

        $row = $this->firstProcessRow((int) $order->getKey());

        $this->assertSame('17.00', $row['unit_net_price']);
        // Parametr to nazwa wybranej pozycji — ta sama, ktora zobaczy
        // operator na hali.
        $this->assertSame('Faza 30mm', $row['parameter']);
    }

    #[Test]
    public function pozycja_spoza_listy_jest_odrzucona(): void
    {
        $order = $this->order();

        $this->position('F', 'Faza 15mm', 8.0, '8.00');
        $foreign = $this->position('K', 'Lakier RAL 9005', 8.0, '40.00');

        /** @var Process $bevel */
        $bevel = Process::findByCode('F');

        $this->service->savePane((int) $order->getKey(), $this->pane([[
            'process_id' => $bevel->id,
            'product_id' => $foreign->id,
        ]]));

        $row = $this->firstProcessRow((int) $order->getKey());

        // Lakier nie jest faza. Zamiast wyceniac fazowanie cena lakieru,
        // zostawiamy etap niewyceniony.
        $this->assertNull($row['product_id']);
        $this->assertSame('0.00', $row['amount']);
    }

    #[Test]
    public function ten_sam_proces_moze_wystapic_dwa_razy(): void
    {
        $order = $this->order();

        $narrow = $this->position('F', 'Faza 15mm', 8.0, '8.00');
        $wide = $this->position('F', 'Faza 30mm', 8.0, '17.00');

        /** @var Process $bevel */
        $bevel = Process::findByCode('F');

        $this->service->savePane((int) $order->getKey(), $this->pane([
            ['process_id' => $bevel->id, 'product_id' => $narrow->id],
            ['process_id' => $bevel->id, 'product_id' => $wide->id],
        ]));

        // Dwie rozne fazy na jednej szybie to dwie rozne prace o roznej
        // cenie, a nie jedna pozycja zapisana dwa razy.
        $this->assertSame(2, OrderItemProcess::query()->count());

        $board = $this->service->board((int) $order->getKey());
        $this->assertCount(2, $board['lists'][0]['glass'][0]['processes']);
    }

    #[Test]
    public function cena_wpisana_recznie_wygrywa_z_cennikiem(): void
    {
        $order = $this->order();
        $cutting = $this->position('C', 'Cięcie 8mm', 8.0, '0.75');

        /** @var Process $process */
        $process = Process::findByCode('C');

        $this->service->savePane((int) $order->getKey(), $this->pane([[
            'process_id' => $process->id,
            'product_id' => $cutting->id,
            'unit_net_price' => '2.50',
        ]]));

        $this->assertSame('2.50', $this->firstProcessRow((int) $order->getKey())['unit_net_price']);
    }

    #[Test]
    public function dni_biora_sie_ze_slownika_i_sumuja_na_formatce(): void
    {
        $order = $this->order();
        $this->position('C', 'Cięcie 8mm', 8.0, '0.75');
        $this->position('P', 'Poler 8mm', 8.0, '3.75');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');
        /** @var Process $polish */
        $polish = Process::findByCode('P');

        $this->service->savePane((int) $order->getKey(), $this->pane([
            ['process_id' => $cutting->id],
            ['process_id' => $polish->id],
        ]));

        // Slownik: ciecie 2 dni, poler 3 dni.
        $board = $this->service->board((int) $order->getKey());

        $this->assertSame(5, $board['lists'][0]['glass'][0]['days']);
        $this->assertSame(5, $board['totals']['days']);
    }

    #[Test]
    public function wpisane_dni_nadpisuja_slownik(): void
    {
        $order = $this->order();
        $this->position('C', 'Cięcie 8mm', 8.0, '0.75');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $this->service->savePane((int) $order->getKey(), $this->pane([[
            'process_id' => $cutting->id,
            'days' => 9,
        ]]));

        $this->assertSame(9, $this->firstProcessRow((int) $order->getKey())['days']);
    }

    #[Test]
    public function zlecenie_trwa_tyle_co_najdluzsza_formatka(): void
    {
        $order = $this->order();
        $this->position('C', 'Cięcie 8mm', 8.0, '0.75');
        $this->position('R', 'CNC 60min', null, '150.00');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');
        /** @var Process $cnc */
        $cnc = Process::findByCode('R');

        $this->service->savePane((int) $order->getKey(), $this->pane([
            ['process_id' => $cutting->id],
        ]));
        $this->service->savePane((int) $order->getKey(), $this->pane([
            ['process_id' => $cutting->id],
            ['process_id' => $cnc->id],
        ]));

        // Formatki ida przez hale rownolegle: 2 i 14 dni to 14, nie 16.
        $this->assertSame(14, (new OrderSchedule())->days($order->fresh() ?? $order));
    }

    #[Test]
    public function dwie_fazy_daja_dwa_zadania_produkcyjne(): void
    {
        $order = $this->order();
        $narrow = $this->position('F', 'Faza 15mm', 8.0, '8.00');
        $wide = $this->position('F', 'Faza 30mm', 8.0, '17.00');

        /** @var Process $bevel */
        $bevel = Process::findByCode('F');

        $this->service->savePane((int) $order->getKey(), $this->pane([
            ['process_id' => $bevel->id, 'product_id' => $narrow->id],
            ['process_id' => $bevel->id, 'product_id' => $wide->id],
        ]));

        (new ProductionPlan())->sync($order->fresh(['lists.items.processes']) ?? $order);

        $this->assertSame(2, ProductionTask::query()->count());
        $this->assertSame(
            ['Faza 15mm', 'Faza 30mm'],
            ProductionTask::query()->orderBy('id')->pluck('parameter')->all(),
        );
    }

    #[Test]
    public function przepisanie_formatki_nie_kasuje_wykonanego_zadania(): void
    {
        $order = $this->order();
        $this->position('C', 'Cięcie 8mm', 8.0, '0.75');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');

        $saved = $this->service->savePane((int) $order->getKey(), $this->pane([
            ['process_id' => $cutting->id],
        ]));

        $plan = new ProductionPlan();
        $plan->sync($order->fresh(['lists.items.processes']) ?? $order);

        /** @var ProductionTask $task */
        $task = ProductionTask::query()->firstOrFail();
        (new TaskExecution())->finish((int) $task->getKey());

        // Poprawka wymiaru kasuje wiersze marszruty i zaklada je od nowa.
        // Zadanie ma to przezyc i dostac wskazanie na nowy wiersz.
        $this->service->savePane(
            (int) $order->getKey(),
            ['width_mm' => 1200] + $this->pane([['process_id' => $cutting->id]]),
            (int) $saved['id'],
        );

        $plan->sync($order->fresh(['lists.items.processes']) ?? $order);

        $this->assertSame(1, ProductionTask::query()->count());

        /** @var ProductionTask $after */
        $after = ProductionTask::query()->firstOrFail();

        $this->assertSame(ProductionStatus::DONE, $after->status);
        $this->assertSame(
            (int) OrderItemProcess::query()->firstOrFail()->getKey(),
            $after->order_item_process_id,
        );
    }
}
