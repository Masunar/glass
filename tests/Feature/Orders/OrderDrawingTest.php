<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Enum\Section;
use App\Enum\PaneShape;
use App\Models\Process;
use App\Models\OrderPane;
use App\Models\OrderItemProcess;
use App\Services\Orders\DrawingRequirement;
use App\Services\Alerts\AlertEngine;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Models\OrderDrawing;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderNextStep;
use App\Services\Orders\OrderDrawingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Rysunki zlecenia i oświadczenie o komplecie.
 *
 * Sedno: komplet deklaruje człowiek, nie licznik plików — ale deklaracja
 * przestaje obowiązywać, gdy komplet się zmieni. Bez tego na produkcję
 * poszłoby zlecenie, którego nikt nie obejrzał w nowym kształcie.
 */
class OrderDrawingTest extends TestCase
{
    use RefreshDatabase;

    private OrderDrawingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        Storage::fake('local');

        $this->service = new OrderDrawingService();
    }

    /**
     * Zlecenie z jedną formatką. Domyślnie z kształtem — takie wymaga
     * rysunków, a o nie chodzi w większości testów tej klasy.
     *
     * @param list<string> $processCodes
     */
    private function order(
        string $statusCode = 'ZLECENIE',
        PaneShape $shape = PaneShape::IRREGULAR,
        array $processCodes = [],
        bool $included = true,
    ): Order {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, $statusCode);

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Rysunki ' . random_int(1000, 9999),
            'tax_id' => '8522347066',
        ]);

        /** @var Order $order */
        $order = Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
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
            'unit_net_price' => '1000.00',
            'amount' => '1000.00',
        ]);

        OrderPane::query()->create([
            'order_item_id' => $item->id,
            'width_mm' => 1000,
            'height_mm' => 800,
            'shape' => $shape->value,
        ]);

        foreach ($processCodes as $position => $code) {
            /** @var Process $process */
            $process = Process::findByCode($code);

            OrderItemProcess::query()->create([
                'order_item_id' => $item->id,
                'process_id' => $process->id,
                'unit_net_price' => '10.00',
                'amount' => '10.00',
                'position' => ($position + 1) * 10,
            ]);
        }

        return $order;
    }

    private function add(Order $order, ?int $itemId = null, string $name = 'rysunek.pdf'): int
    {
        $result = $this->service->store(
            (int) $order->getKey(),
            UploadedFile::fake()->create($name, 120, 'application/pdf'),
            $itemId,
            null,
        );

        $this->assertSame([], $result['errors']);

        return (int) $result['id'];
    }

    #[Test]
    public function rysunek_zapisuje_sie_pod_wlasna_nazwa_a_pokazuje_pod_wgrana(): void
    {
        $order = $this->order();
        $id = $this->add($order, name: 'elewacja.pdf');

        /** @var OrderDrawing $drawing */
        $drawing = OrderDrawing::query()->findOrFail($id);

        $this->assertSame('elewacja.pdf', $drawing->original_name);
        $this->assertNotSame('elewacja.pdf', basename($drawing->stored_path));
        Storage::disk('local')->assertExists($drawing->stored_path);
    }

    #[Test]
    public function rysunek_moze_dotyczyc_calego_zlecenia(): void
    {
        $order = $this->order();
        $id = $this->add($order);

        // Puste pole pozycji znaczy „dotyczy calego zlecenia" — wymuszanie
        // wyboru konczy sie przypisaniem szkicu do pierwszej z brzegu.
        $this->assertNull(OrderDrawing::query()->findOrFail($id)->order_item_id);
    }

    #[Test]
    public function rysunek_z_cudzej_pozycji_jest_odrzucony(): void
    {
        $mine = $this->order();
        $other = $this->order();

        /** @var OrderItem $foreign */
        $foreign = OrderItem::query()
            ->whereHas('list', static fn($q) => $q->where('order_id', $other->getKey()))
            ->firstOrFail();

        $result = $this->service->store(
            (int) $mine->getKey(),
            UploadedFile::fake()->create('rys.pdf', 10, 'application/pdf'),
            $foreign->getKey(),
            null,
        );

        $this->assertArrayHasKey('order_item_id', $result['errors']);
    }

    #[Test]
    public function plik_w_niedozwolonym_formacie_jest_odrzucony(): void
    {
        $order = $this->order();

        $result = $this->service->store(
            (int) $order->getKey(),
            UploadedFile::fake()->create('makro.exe', 10, 'application/octet-stream'),
            null,
            null,
        );

        $this->assertArrayHasKey('file', $result['errors']);
        $this->assertSame(0, OrderDrawing::query()->count());
    }

    #[Test]
    public function oswiadczenie_o_komplecie_zapisuje_kto_i_kiedy(): void
    {
        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Paulina',
            'last_name' => 'Zabora',
            'email' => 'pz' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
        ]);
        $this->actingAs($user);

        $order = $this->order();
        $this->service->declare((int) $order->getKey(), true);

        $fresh = Order::query()->findOrFail($order->getKey());

        $this->assertNotNull($fresh->drawings_complete_at);
        $this->assertSame((int) $user->getKey(), $fresh->drawings_complete_by);

        $board = $this->service->board((int) $order->getKey());
        $this->assertTrue($board['complete']['declared']);
        $this->assertSame('Paulina Zabora', $board['complete']['by']);
    }

    #[Test]
    public function oswiadczenie_odblokowuje_przejscie_do_produkcji(): void
    {
        $order = $this->order();
        $steps = new OrderNextStep();

        $blocked = $this->productionStep($order, $steps);
        $this->assertNotNull($blocked);
        $this->assertSame('Nie zaznaczono, że wszystkie rysunki są dodane.', $blocked->blockedBy);

        $this->service->declare((int) $order->getKey(), true);

        // Rysunki przestaja blokowac; zostaje warunek zaliczki, ktory
        // czeka na modul wplat — ale to juz inny powod.
        $after = $this->productionStep($order->fresh() ?? $order, $steps);
        $this->assertNotNull($after);
        $this->assertNotSame('Nie zaznaczono, że wszystkie rysunki są dodane.', $after->blockedBy);
    }

    #[Test]
    public function dodanie_rysunku_cofa_wczesniejsze_oswiadczenie(): void
    {
        $order = $this->order();
        $this->service->declare((int) $order->getKey(), true);

        $this->add($order);

        $this->assertNull(Order::query()->findOrFail($order->getKey())->drawings_complete_at);
    }

    #[Test]
    public function usuniecie_rysunku_tez_cofa_oswiadczenie(): void
    {
        $order = $this->order();
        $id = $this->add($order);
        $this->service->declare((int) $order->getKey(), true);

        $this->service->delete((int) $order->getKey(), $id);

        $this->assertNull(Order::query()->findOrFail($order->getKey())->drawings_complete_at);
    }

    #[Test]
    public function usuniecie_kasuje_plik_z_dysku(): void
    {
        $order = $this->order();
        $id = $this->add($order);

        /** @var OrderDrawing $drawing */
        $drawing = OrderDrawing::query()->findOrFail($id);
        $path = $drawing->stored_path;

        $this->service->delete((int) $order->getKey(), $id);

        Storage::disk('local')->assertMissing($path);
        $this->assertNull(OrderDrawing::query()->find($id));
    }

    #[Test]
    public function rysunku_z_cudzego_zlecenia_nie_da_sie_skasowac(): void
    {
        $mine = $this->order();
        $other = $this->order();
        $id = $this->add($other);

        $result = $this->service->delete((int) $mine->getKey(), $id);

        $this->assertNotSame([], $result['errors']);
        $this->assertNotNull(OrderDrawing::query()->find($id));
    }

    #[Test]
    public function cofniecie_oswiadczenia_czysci_osobe_i_date(): void
    {
        $order = $this->order();
        $this->service->declare((int) $order->getKey(), true);
        $this->service->declare((int) $order->getKey(), false);

        $fresh = Order::query()->findOrFail($order->getKey());

        $this->assertNull($fresh->drawings_complete_at);
        $this->assertNull($fresh->drawings_complete_by);
    }

    #[Test]
    public function prostokat_bez_procesow_z_rysunkiem_nie_czeka_na_oswiadczenie(): void
    {
        $order = $this->order(shape: PaneShape::RECTANGLE, processCodes: ['C', 'S']);

        $step = $this->productionStep($order, new OrderNextStep());

        // Proste docinki nie maja czego rysowac. Oswiadczenie o komplecie
        // niczego tu nie zabezpiecza, a blokowalo produkcje.
        $this->assertNotNull($step);
        $this->assertNotSame('Nie zaznaczono, że wszystkie rysunki są dodane.', $step->blockedBy);
        $this->assertSame([], $this->service->board((int) $order->getKey())['required']);
    }

    #[Test]
    public function proces_z_rysunkiem_wymaga_oswiadczenia(): void
    {
        $order = $this->order(shape: PaneShape::RECTANGLE, processCodes: ['C', 'R']);

        $step = $this->productionStep($order, new OrderNextStep());

        $this->assertNotNull($step);
        $this->assertSame('Nie zaznaczono, że wszystkie rysunki są dodane.', $step->blockedBy);
        $this->assertSame(['CNC'], $this->service->board((int) $order->getKey())['required']);
    }

    #[Test]
    public function owal_wymaga_rysunku_i_mowi_o_tym_na_zakladce(): void
    {
        $order = $this->order(shape: PaneShape::OVAL);

        $this->assertSame(['Owal: 1 formatka'], $this->service->board((int) $order->getKey())['required']);
    }

    #[Test]
    public function wariant_niewliczony_nie_wymaga_rysunku(): void
    {
        $order = $this->order(shape: PaneShape::IRREGULAR, processCodes: ['R'], included: false);

        // Alternatywa z oferty nie pojedzie na hale — nie ma czego z niej
        // wycinac.
        $this->assertFalse((new DrawingRequirement())->requires($order));
    }

    #[Test]
    public function alert_i_przejscie_licza_z_tej_samej_reguly(): void
    {
        $orders = [
            $this->order(shape: PaneShape::RECTANGLE),
            $this->order(shape: PaneShape::RECTANGLE, processCodes: ['W']),
            $this->order(shape: PaneShape::IRREGULAR),
            $this->order(shape: PaneShape::OVAL, included: false),
            $this->order(shape: PaneShape::RECTANGLE, processCodes: ['C', 'S', 'P']),
        ];

        $requirement = new DrawingRequirement();

        /** @var list<int> $inSql */
        $inSql = $requirement->scope(Order::query())->pluck('id')->map(static fn($id): int => (int) $id)->all();

        $alerted = [];

        foreach ((new AlertEngine())->run() as $row) {
            if ($row['code'] === 'order_missing_drawings') {
                $alerted[] = (int) $row['alertable_id'];
            }
        }

        foreach ($orders as $order) {
            $id = (int) $order->getKey();
            $needs = $requirement->requires(Order::query()->findOrFail($id));

            // Zapytanie dla alertu i odczyt z pozycji dla przejscia maja
            // opisywac ten sam zbior. Alert „brak rysunkow" przy zleceniu,
            // ktore przechodzi do produkcji bez nich, bylby rozjazdem.
            $this->assertSame($needs, in_array($id, $inSql, true), 'Zlecenie #' . $order->number);
            $this->assertSame($needs, in_array($id, $alerted, true), 'Alert przy #' . $order->number);
        }

        $this->assertSame(2, count($inSql));
    }

    private function productionStep(Order $order, OrderNextStep $steps): ?\App\DTO\Orders\NextStep
    {
        foreach ($steps->forOrder($order) as $step) {
            if ($step->target->code === 'PRODUKCJA') {
                return $step;
            }
        }

        return null;
    }
}
