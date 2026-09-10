<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Models\Order;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Models\OrderPane;
use App\Support\Normalize;
use App\Services\AuditTrail;
use App\Models\OrderItemProcess;
use App\DTO\Pricing\PaneSpecification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Formatki i usługi na zleceniu.
 *
 * Cena pozycji jest **snapshotem**, nie odwołaniem do cennika: oferta
 * wystawiona wczoraj musi pokazywać tę samą kwotę po dzisiejszej
 * dostawie, która zmieniła cenę zakupu. Razem z kwotą zapisuje się
 * ścieżka wyliczenia — przy czterech nakładających się poziomach ceny
 * bez niej nie da się odpowiedzieć, skąd wzięła się ta liczba.
 *
 * Przeliczenie następuje wyłącznie przy zapisie pozycji. Nie ma tu
 * żadnego „odśwież ceny", bo to właśnie ciche przeliczanie było
 * największą wadą starego cennika.
 */
final readonly class OrderItemService
{
    private const MAX_MM = 6000;

    public function __construct(
        private OrderPricing $pricing = new OrderPricing(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Ekran formatek: listy z pozycjami, sumy i słowniki potrzebne
     * formularzowi.
     *
     * @return array<string, mixed>
     */
    public function board(int $orderId): array
    {
        /** @var Order $order */
        $order = Order::query()
            ->with([
                'contractor',
                'status',
                'invoiceType',
                'lists.items.pane',
                'lists.items.product.group',
                'lists.items.processes.process',
            ])
            ->findOrFail($orderId);

        $lists = [];
        $net = 0.0;
        $squareMeters = 0.0;
        $runningMeters = 0.0;
        $weight = 0.0;

        /** @var OrderList $list */
        foreach ($order->lists->sortBy('number') as $list) {
            $glass = [];
            $services = [];
            $listNet = 0.0;

            /** @var OrderItem $item */
            foreach ($list->items->sortBy('position') as $item) {
                $row = $this->row($item);
                $listNet += (float) $row['total'];

                if ($item->section === Section::GLASS) {
                    $glass[] = $row;

                    if ($list->is_included) {
                        $squareMeters += (float) $row['m2'];
                        $runningMeters += (float) $row['mb'];
                        $weight += (float) $row['kg'];
                    }

                    continue;
                }

                $services[] = $row;
            }

            if ($list->is_included) {
                $net += $listNet;
            }

            $lists[] = [
                'id' => (int) $list->getKey(),
                'number' => (int) $list->number,
                'name' => $list->name,
                'role' => $list->role->value,
                'is_included' => (bool) $list->is_included,
                'is_on_hold' => (bool) $list->is_on_hold,
                'comment' => $list->comment,
                'net' => $this->money($listNet),
                'glass' => $glass,
                'services' => $services,
            ];
        }

        $vatRate = $order->invoiceType?->vat_rate;

        return [
            'order' => [
                'id' => (int) $order->getKey(),
                'number' => (int) $order->number,
                'status' => $order->status?->name,
                'contractor' => $order->contractor?->displayName(),
            ],
            'lists' => $lists,
            'totals' => [
                'net' => $this->money($net),
                'vat_rate' => $vatRate,
                'gross' => $vatRate === null
                    ? null
                    : $this->money($net * (100 + $vatRate) / 100),
                'm2' => round($squareMeters, 2),
                'mb' => round($runningMeters, 2),
                'kg' => round($weight, 2),
            ],
            'catalogue' => [
                'products' => $this->glassCatalogue(),
                'processes' => $this->processCatalogue(),
                'services' => $this->serviceCatalogue(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function savePane(int $orderId, array $input, ?int $itemId = null): array
    {
        /** @var Order $order */
        $order = Order::query()->with('contractor')->findOrFail($orderId);

        $errors = $this->validatePane($input);

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        $list = $this->listFor($order, $input['order_list_id'] ?? null, $itemId);

        if ($list === null) {
            return ['errors' => ['order_list_id' => ['Taka lista nie należy do tego zlecenia.']], 'id' => null];
        }

        /** @var Product|null $product */
        $product = Product::query()
            ->with('glass')
            ->where('section', Section::GLASS->value)
            ->where('is_active', true)
            ->find((int) $input['product_id']);

        if ($product === null) {
            return ['errors' => ['product_id' => ['Taki materiał nie jest w cenniku szkła.']], 'id' => null];
        }

        $processIds = $this->processIds($input['processes'] ?? []);

        $pane = new PaneSpecification(
            widthMm: (int) $input['width_mm'],
            heightMm: (int) $input['height_mm'],
            quantity: (int) ($input['quantity'] ?? 1),
            isIrregularShape: (bool) ($input['is_irregular_shape'] ?? false),
            isTempered: (bool) ($input['is_tempered'] ?? false),
        );

        $price = $this->pricing->pane($order, $product, $pane, $processIds);

        $item = DB::transaction(function () use (
            $list,
            $product,
            $pane,
            $price,
            $input,
            $itemId,
        ): OrderItem {
            $item = $itemId === null
                ? new OrderItem()
                : OrderItem::query()->findOrFail($itemId);

            $item->fill([
                'order_list_id' => (int) $list->getKey(),
                'product_id' => (int) $product->getKey(),
                'section' => Section::GLASS->value,
                'name' => $product->name,
                'quantity' => $pane->quantity,
                // Dla szkla cena jednostkowa to cena metra kwadratowego —
                // to ona wynika z cennika. Kwota pozycji jest wynikiem
                // wzoru, nie mnozenia ceny przez ilosc.
                'unit_net_price' => $price->netPricePerSquareMeter ?? '0.00',
                'amount' => $price->glassNet,
                'price_path' => $price->steps,
                'position' => $item->exists
                    ? $item->position
                    : $this->nextPosition($list),
            ]);
            $item->save();

            OrderPane::query()->updateOrCreate(
                ['order_item_id' => (int) $item->getKey()],
                [
                    'width_mm' => $pane->widthMm,
                    'height_mm' => $pane->heightMm,
                    'is_irregular_shape' => $pane->isIrregularShape,
                    'is_tempered' => $pane->isTempered,
                    'needs_mark' => (bool) ($input['needs_mark'] ?? false),
                ],
            );

            // Procesy zapisujemy od nowa: zmiana wymiarow zmienia kwote
            // kazdego z nich, wiec aktualizacja wiersz po wierszu i tak
            // dotknelaby wszystkich.
            OrderItemProcess::query()->where('order_item_id', $item->getKey())->delete();

            $position = 0;

            foreach ($price->processes as $process) {
                OrderItemProcess::query()->create([
                    'order_item_id' => (int) $item->getKey(),
                    'process_id' => $process['process_id'],
                    'unit_net_price' => $process['unit_net_price'],
                    'amount' => $process['amount'],
                    'position' => $position += 10,
                ]);
            }

            return $item;
        });

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'pozycja #' . $item->getKey(),
                'before' => $itemId === null ? null : 'wycena',
                'after' => sprintf(
                    '%s, %d × %d mm × %d szt., %s zł',
                    $product->name,
                    $pane->widthMm,
                    $pane->heightMm,
                    $pane->quantity,
                    $price->total(),
                ),
            ]],
            $itemId === null ? 'item_added' : 'item_updated',
        );

        return ['errors' => [], 'id' => (int) $item->getKey()];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function saveService(int $orderId, array $input, ?int $itemId = null): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'unit_net_price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'product_id' => ['nullable', 'integer'],
        ], [
            'name.required' => 'Podaj nazwę usługi.',
            'quantity.required' => 'Podaj ilość.',
            'unit_net_price.required' => 'Podaj cenę netto.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'id' => null];
        }

        $list = $this->listFor($order, $input['order_list_id'] ?? null, $itemId);

        if ($list === null) {
            return ['errors' => ['order_list_id' => ['Taka lista nie należy do tego zlecenia.']], 'id' => null];
        }

        $quantity = (float) $input['quantity'];
        $unit = (float) $input['unit_net_price'];

        $item = $itemId === null ? new OrderItem() : OrderItem::query()->findOrFail($itemId);

        $item->fill([
            'order_list_id' => (int) $list->getKey(),
            'product_id' => $this->id($input['product_id'] ?? null),
            'section' => Section::SERVICES->value,
            'name' => Normalize::text($input['name']) ?? '',
            'quantity' => $this->money($quantity),
            'unit_net_price' => $this->money($unit),
            'amount' => $this->money($quantity * $unit),
            'position' => $item->exists ? $item->position : $this->nextPosition($list),
        ]);
        $item->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'usługa #' . $item->getKey(),
                'before' => $itemId === null ? null : 'wycena',
                'after' => sprintf('%s, %s zł', $item->name, $this->money($quantity * $unit)),
            ]],
            $itemId === null ? 'item_added' : 'item_updated',
        );

        return ['errors' => [], 'id' => (int) $item->getKey()];
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function delete(int $orderId, int $itemId): array
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        /** @var OrderItem|null $item */
        $item = OrderItem::query()
            ->whereHas('list', static fn($query) => $query->where('order_id', $order->getKey()))
            ->find($itemId);

        if ($item === null) {
            return ['errors' => ['item' => ['Ta pozycja nie należy do tego zlecenia.']]];
        }

        $label = $item->name;
        $item->delete();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'pozycja #' . $itemId, 'before' => $label, 'after' => null]],
            'item_removed',
        );

        return ['errors' => []];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function validatePane(array $input): array
    {
        $validator = Validator::make($input, [
            'product_id' => ['required', 'integer'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:' . self::MAX_MM],
            'height_mm' => ['required', 'integer', 'min:1', 'max:' . self::MAX_MM],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'processes' => ['nullable', 'array'],
            'processes.*' => ['integer'],
        ], [
            'product_id.required' => 'Wskaż materiał.',
            'width_mm.required' => 'Podaj szerokość w milimetrach.',
            'height_mm.required' => 'Podaj wysokość w milimetrach.',
            'width_mm.max' => 'Szerokość powyżej ' . self::MAX_MM . ' mm nie przejdzie przez halę.',
            'height_mm.max' => 'Wysokość powyżej ' . self::MAX_MM . ' mm nie przejdzie przez halę.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return $messages;
        }

        return [];
    }

    /**
     * Lista, do której trafia pozycja. Przy edycji zostaje ta, w której
     * pozycja już jest — przenoszenie między listami to osobna decyzja.
     */
    private function listFor(Order $order, mixed $listId, ?int $itemId): ?OrderList
    {
        if ($itemId !== null) {
            /** @var OrderItem|null $item */
            $item = OrderItem::query()->find($itemId);
            $list = $item?->list;

            return $list instanceof OrderList && $list->order_id === $order->getKey() ? $list : null;
        }

        $id = $this->id($listId);

        /** @var OrderList|null */
        return OrderList::query()
            ->where('order_id', $order->getKey())
            ->when($id !== null, static fn($query) => $query->whereKey($id))
            ->orderBy('number')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(OrderItem $item): array
    {
        $processes = [];
        $processAmount = 0.0;

        foreach ($item->processes->sortBy('position') as $entry) {
            $processes[] = [
                'process_id' => (int) $entry->process_id,
                'code' => $entry->process?->code,
                'name' => $entry->process?->name,
                'unit_net_price' => $entry->unit_net_price,
                'amount' => $entry->amount,
            ];

            $processAmount += (float) $entry->amount;
        }

        $pane = $item->pane;
        $spec = $pane === null
            ? null
            : new PaneSpecification(
                widthMm: $pane->width_mm,
                heightMm: $pane->height_mm,
                quantity: (int) $item->quantity,
                isIrregularShape: (bool) $pane->is_irregular_shape,
                isTempered: (bool) $pane->is_tempered,
            );

        $thickness = $item->product?->glass?->thickness_mm;

        return [
            'id' => (int) $item->getKey(),
            'position' => $item->position,
            'section' => $item->section->value,
            'product_id' => $item->product_id,
            'group' => $item->product?->group?->name,
            'name' => $item->name,
            'thickness_mm' => $thickness,
            'quantity' => $item->quantity,
            'unit_net_price' => $item->unit_net_price,
            'amount' => $item->amount,
            'total' => $this->money((float) $item->amount + $processAmount),
            'processes' => $processes,
            'price_path' => $item->price_path ?? [],
            'width_mm' => $pane?->width_mm,
            'height_mm' => $pane?->height_mm,
            'is_irregular_shape' => (bool) ($pane?->is_irregular_shape ?? false),
            'is_tempered' => (bool) ($pane?->is_tempered ?? false),
            'needs_mark' => (bool) ($pane?->needs_mark ?? false),
            'm2' => $spec === null ? null : round($spec->squareMeters(), 3),
            'mb' => $spec === null ? null : round($spec->runningMeters(), 2),
            'kg' => $spec === null || $thickness === null
                ? null
                : $item->product?->glass?->weightOfPane(
                    $spec->widthMm,
                    $spec->heightMm,
                    $spec->quantity,
                ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function glassCatalogue(): array
    {
        $rows = [];

        /** @var iterable<Product> $products */
        $products = OrderPricing::glassProducts()->with('group')->get();

        foreach ($products as $product) {
            $rows[] = [
                'id' => (int) $product->getKey(),
                'name' => $product->name,
                'group' => $product->group?->name,
                'thickness_mm' => $product->glass?->thickness_mm,
                'is_tempered_by_default' => (bool) ($product->glass?->is_tempered_by_default ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function processCatalogue(): array
    {
        $rows = [];

        /** @var iterable<Process> $processes */
        $processes = Process::query()
            ->where('is_active', true)
            ->orderBy('default_order')
            ->get();

        foreach ($processes as $process) {
            $rows[] = [
                'id' => (int) $process->getKey(),
                'code' => $process->code,
                'name' => $process->name,
                'is_subcontracted' => (bool) $process->is_subcontracted,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serviceCatalogue(): array
    {
        $rows = [];

        /** @var iterable<Product> $products */
        $products = Product::query()
            ->where('section', Section::SERVICES->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $rows[] = ['id' => (int) $product->getKey(), 'name' => $product->name];
        }

        return $rows;
    }

    /**
     * @param mixed $processes
     * @return list<int>
     */
    private function processIds(mixed $processes): array
    {
        if (!is_array($processes)) {
            return [];
        }

        $ids = [];

        foreach ($processes as $value) {
            $id = (int) $value;

            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function nextPosition(OrderList $list): int
    {
        return ((int) OrderItem::query()
            ->where('order_list_id', $list->getKey())
            ->max('position')) + 10;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function id(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}
