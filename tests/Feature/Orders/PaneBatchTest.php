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
use App\Models\OrderItem;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use App\Models\PriceSection;
use App\Models\ProductGroup;
use App\Models\ProductService;
use App\Models\PurchasePrice;
use App\Models\OrderItemProcess;
use App\Enum\PurchasePriceSource;
use App\Services\PriceListService;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\PaneMaterialSwap;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Wycena hurtem i „Zamień wszystko" (uwaga klienta z 25.09).
 *
 * Paczka formatek ma dać dokładnie to samo, co dodanie ich po kolei,
 * a zamiana materiału — dopasować etapy do nowej grubości bez zgadywania
 * spośród kilku pozycji.
 */
class PaneBatchTest extends TestCase
{
    use RefreshDatabase;

    private OrderItemService $items;

    protected function setUp(): void
    {
        parent::setUp();

        Order::query()->delete();
        $this->items = new OrderItemService();
        $this->priceGlass('float 8mm');
        $this->priceGlass('float 10mm');
    }

    private function glass(string $name): Product
    {
        /** @var Product */
        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function section(string $section): PriceSection
    {
        /** @var PriceSection */
        return PriceSection::query()
            ->where('section', $section)
            ->where('name', 'Detaliczny podstawowy')
            ->firstOrFail();
    }

    private function priceGlass(string $name): void
    {
        (new PriceListService())->update([[
            'product_id' => $this->glass($name)->id,
            'price_section_id' => $this->section(Section::GLASS->value)->id,
            'coefficient' => '4.0',
        ]]);
    }

    /** Pozycja cennikowa procesu dla grubości (null = każda). */
    private function position(string $processCode, string $name, ?float $thickness, string $purchaseNet): Product
    {
        /** @var Process $process */
        $process = Process::findByCode($processCode);

        /** @var ProductGroup $group */
        $group = ProductGroup::query()->firstOrCreate(
            ['section' => Section::SERVICES->value, 'name' => 'Obróbka krawędzi'],
            ['position' => 10],
        );

        /** @var Product $product */
        $product = Product::query()->create([
            'product_group_id' => $group->id,
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
            'name' => 'Hurt ' . random_int(1000, 9999),
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
     * @param list<array<string, mixed>> $processes
     * @param list<array<string, int>> $sizes
     * @return array<string, mixed>
     */
    private function batch(array $sizes, array $processes = [], string $glass = 'float 8mm'): array
    {
        return [
            'product_id' => $this->glass($glass)->id,
            'is_tempered' => false,
            'processes' => $processes,
            'sizes' => $sizes,
        ];
    }

    /**
     * @return list<OrderItem>
     */
    private function panes(Order $order): array
    {
        /** @var list<OrderItem> */
        return OrderItem::query()
            ->with(['pane', 'processes'])
            ->whereHas('list', static fn($query) => $query->where('order_id', $order->getKey()))
            ->orderBy('position')
            ->get()
            ->all();
    }

    #[Test]
    public function paczka_formatek_zapisuje_sie_jednym_ruchem(): void
    {
        $this->position('C', 'Cięcie 8mm', 8.0, '0.75');
        /** @var Process $cutting */
        $cutting = Process::findByCode('C');
        $order = $this->order();

        $result = $this->items->savePanes((int) $order->getKey(), $this->batch([
            ['width_mm' => 1000, 'height_mm' => 500, 'quantity' => 2],
            ['width_mm' => 800, 'height_mm' => 600],
            ['width_mm' => 1200, 'height_mm' => 900, 'quantity' => 4],
        ], [['process_id' => $cutting->id]]));

        $this->assertSame([], $result['errors']);
        $this->assertCount(3, $result['ids']);

        $panes = $this->panes($order);
        $this->assertCount(3, $panes);
        $this->assertSame([1000, 800, 1200], array_map(static fn(OrderItem $item): ?int => $item->pane?->width_mm, $panes));
        $this->assertSame([2, 1, 4], array_map(static fn(OrderItem $item): int => (int) $item->quantity, $panes));

        foreach ($panes as $pane) {
            $this->assertSame($this->glass('float 8mm')->id, $pane->product_id);
            $this->assertCount(1, $pane->processes);
        }

        $this->assertSame(3, AuditEntry::query()->where('event', 'item_added')->count());
    }

    #[Test]
    public function paczka_liczy_tak_samo_jak_formatki_po_kolei(): void
    {
        $order = $this->order();
        $single = $this->items->preview((int) $order->getKey(), [
            'product_id' => $this->glass('float 8mm')->id,
            'width_mm' => 1000,
            'height_mm' => 500,
            'quantity' => 2,
        ]);

        $batch = $this->items->preview((int) $order->getKey(), $this->batch([
            ['width_mm' => 1000, 'height_mm' => 500, 'quantity' => 2],
            ['width_mm' => 1000, 'height_mm' => 500, 'quantity' => 2],
            ['width_mm' => 0, 'height_mm' => 0],
        ]));

        /** @var array{rows: list<string|null>, count: int, pieces: int, total: string|null} $summary */
        $summary = $batch['batch'];

        $this->assertSame(2, $summary['count']);
        $this->assertSame(4, $summary['pieces']);
        $this->assertSame($single['total'], $summary['rows'][0]);
        // Niekompletny wiersz nie ma kwoty i nie wchodzi do sumy.
        $this->assertNull($summary['rows'][2]);
        $this->assertSame(
            number_format((float) $single['total'] * 2, 2, '.', ''),
            $summary['total'],
        );
    }

    #[Test]
    public function zly_wiersz_zatrzymuje_cala_paczke(): void
    {
        $order = $this->order();

        $result = $this->items->savePanes((int) $order->getKey(), $this->batch([
            ['width_mm' => 1000, 'height_mm' => 500],
            ['width_mm' => 9000, 'height_mm' => 500],
        ]));

        $this->assertArrayHasKey('sizes.1.width_mm', $result['errors']);
        $this->assertSame([], $this->panes($order));
    }

    #[Test]
    public function zamiana_dopasowuje_etapy_do_nowej_grubosci(): void
    {
        // Ciecie: po jednej pozycji na grubosc — jedyny kandydat.
        $this->position('C', 'Cięcie 8mm', 8.0, '0.75');
        $cut10 = $this->position('C', 'Cięcie 10mm', 10.0, '0.90');
        // Faza: dwie pozycje dla 10 mm — wygrywa ta o tej samej nazwie.
        $this->position('F', 'Faza 15mm', 8.0, '5.00');
        $bevel10 = $this->position('F', 'Faza 15mm', 10.0, '6.00');
        $this->position('F', 'Faza 20mm', 10.0, '7.00');
        // CNC bez grubosci zostaje soba.
        $cnc = $this->position('R', 'CNC 60min', null, '150.00');

        /** @var Process $cutting */
        $cutting = Process::findByCode('C');
        /** @var Process $bevel */
        $bevel = Process::findByCode('F');
        /** @var Process $router */
        $router = Process::findByCode('R');

        $order = $this->order();
        $bevel8 = Product::query()->where('name', 'Faza 15mm')->orderBy('id')->firstOrFail();

        $saved = $this->items->savePanes((int) $order->getKey(), $this->batch([
            ['width_mm' => 1000, 'height_mm' => 500],
            ['width_mm' => 700, 'height_mm' => 400],
        ], [
            ['process_id' => $cutting->id, 'days' => 3],
            ['process_id' => $bevel->id, 'product_id' => $bevel8->id],
            ['process_id' => $router->id, 'unit_net_price' => '999.00'],
        ]));

        $this->assertSame([], $saved['errors']);

        $swap = new PaneMaterialSwap();
        $input = ['product_id' => $this->glass('float 10mm')->id, 'item_ids' => $saved['ids']];

        $preview = $swap->preview((int) $order->getKey(), $input);
        $this->assertSame([], $preview['errors']);
        $this->assertSame(0, $preview['pending']);

        $result = $swap->apply((int) $order->getKey(), $input);
        $this->assertSame(2, $result['changed']);

        foreach ($this->panes($order) as $pane) {
            $this->assertSame($this->glass('float 10mm')->id, $pane->product_id);

            $chosen = $pane->processes->sortBy('position')->pluck('product_id')->all();
            $this->assertSame([$cut10->id, $bevel10->id, $cnc->id], $chosen);

            /** @var OrderItemProcess $cut */
            $cut = $pane->processes->sortBy('position')->first();
            // Dni wpisane recznie zostaja; stawka CNC wraca do cennika.
            $this->assertSame(3, $cut->days);
            $this->assertSame('150.00', $pane->processes->firstWhere('process_id', $router->id)?->unit_net_price);
        }

        $this->assertSame(1, AuditEntry::query()->where('event', 'material_swapped')->count());
        // Przeliczenie idzie tak samo jak recznie: z ta sama suma co podglad.
        $after = 0.0;

        foreach ($this->panes($order) as $pane) {
            $after += (float) $pane->amount + (float) $pane->processes->sum(static fn(OrderItemProcess $entry): float => (float) $entry->amount);
        }

        $this->assertSame($preview['after'], number_format($after, 2, '.', ''));
    }

    #[Test]
    public function kilka_pasujacych_pozycji_czeka_na_czlowieka(): void
    {
        $this->position('F', 'Faza 5mm', 8.0, '4.00');
        $this->position('F', 'Faza 15mm', 10.0, '6.00');
        $this->position('F', 'Faza 20mm', 10.0, '7.00');
        /** @var Process $bevel */
        $bevel = Process::findByCode('F');
        $order = $this->order();

        $saved = $this->items->savePanes((int) $order->getKey(), $this->batch(
            [['width_mm' => 1000, 'height_mm' => 500]],
            [['process_id' => $bevel->id]],
        ));

        $swap = new PaneMaterialSwap();
        $input = ['product_id' => $this->glass('float 10mm')->id, 'item_ids' => $saved['ids']];

        $this->assertSame(1, $swap->preview((int) $order->getKey(), $input)['pending']);

        $swap->apply((int) $order->getKey(), $input);

        $pane = $this->panes($order)[0];
        $this->assertNull($pane->processes->first()?->product_id);
    }

    #[Test]
    public function formatka_z_tego_samego_materialu_zostaje_bez_zmian(): void
    {
        $order = $this->order();
        $saved = $this->items->savePanes((int) $order->getKey(), $this->batch(
            [['width_mm' => 1000, 'height_mm' => 500]],
        ));

        $result = (new PaneMaterialSwap())->apply((int) $order->getKey(), [
            'product_id' => $this->glass('float 8mm')->id,
            'item_ids' => $saved['ids'],
        ]);

        $this->assertSame(0, $result['changed']);
        $this->assertSame(0, AuditEntry::query()->where('event', 'material_swapped')->count());
    }

    #[Test]
    public function cudza_formatka_nie_wchodzi_do_zamiany(): void
    {
        $mine = $this->order();
        $other = $this->order();
        $foreign = $this->items->savePanes((int) $other->getKey(), $this->batch(
            [['width_mm' => 1000, 'height_mm' => 500]],
        ));

        $result = (new PaneMaterialSwap())->apply((int) $mine->getKey(), [
            'product_id' => $this->glass('float 10mm')->id,
            'item_ids' => $foreign['ids'],
        ]);

        $this->assertArrayHasKey('item_ids', $result['errors']);
        $this->assertSame($this->glass('float 8mm')->id, $this->panes($other)[0]->product_id);
    }
}
