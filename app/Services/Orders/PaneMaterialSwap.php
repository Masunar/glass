<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Models\Order;
use App\Models\Process;
use App\Models\Product;
use App\Models\OrderItem;
use App\Services\AuditTrail;
use App\Models\OrderItemProcess;
use App\DTO\Pricing\PaneSpecification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * „Zamień wszystko" — inny materiał w zaznaczonych formatkach.
 *
 * Uwaga klienta (25.09): klient zmienia zdanie z 8 mm na 10 mm i biuro
 * przepisywało formatkę po formatce. Decyzje Marcina: zamiana działa na
 * zaznaczonych formatkach, a etapy dopasowuje się do nowej grubości
 * i wycenia **na nowo z cennika**.
 *
 * Dopasowanie pozycji etapu, po kolei:
 *  1. dotychczasowa pozycja, jeśli pasuje do nowej grubości (CNC bez
 *     grubości zostaje sobą);
 *  2. pozycja o tej samej nazwie („Faza 15mm" dla 10 mm);
 *  3. jedyny kandydat;
 *  4. inaczej etap czeka na człowieka i jest oznaczony jako
 *     niewyceniony — ta sama zasada co przy dodawaniu formatki: automat
 *     nie wybiera spośród kilku.
 *
 * Ceny etapów wpisane ręcznie przepadają — przy innej grubości stara
 * stawka nic nie znaczy. Dlatego najpierw jest podgląd: kwota przed
 * i po oraz liczba etapów do wybrania.
 *
 * Zapis każdej formatki idzie przez `OrderItemService::savePane` —
 * wycena, ścieżka ceny, dziennik i termin liczą się tak samo jak przy
 * ręcznej edycji.
 */
final readonly class PaneMaterialSwap
{
    private const MAX_ITEMS = 200;

    public function __construct(
        private OrderPricing $pricing = new OrderPricing(),
        private OrderItemService $items = new OrderItemService(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Co by się stało — bez zapisu.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preview(int $orderId, array $input): array
    {
        $resolved = $this->resolve($orderId, $input);
        $order = $resolved['order'];
        $product = $resolved['product'];
        $items = $resolved['items'];

        if ($resolved['errors'] !== [] || $order === null || $product === null) {
            return ['errors' => $resolved['errors']];
        }

        $thickness = $product->glass?->thickness_mm;

        $rows = [];
        $before = 0.0;
        $after = 0.0;
        $pending = 0;
        $glassMissing = false;

        foreach ($items as $item) {
            $current = $this->total($item);
            $unchanged = $item->product_id === (int) $product->getKey();

            if ($unchanged) {
                $rows[] = [
                    'id' => (int) $item->getKey(),
                    'name' => $item->name,
                    'before' => $this->money($current),
                    'after' => $this->money($current),
                    'pending' => 0,
                    'unchanged' => true,
                ];
                $before += $current;
                $after += $current;

                continue;
            }

            $price = $this->pricing->pane(
                $order,
                $product,
                $this->specification($item),
                $this->selections($item, $thickness),
            );

            $waiting = 0;

            foreach ($price->processes as $process) {
                if ($process['product_id'] === null) {
                    $waiting++;
                }
            }

            $rows[] = [
                'id' => (int) $item->getKey(),
                'name' => $item->name,
                'before' => $this->money($current),
                'after' => $price->total(),
                'pending' => $waiting,
                'unchanged' => false,
            ];

            $before += $current;
            $after += (float) $price->total();
            $pending += $waiting;
            $glassMissing = $glassMissing || !$price->isAvailable();
        }

        return [
            'errors' => [],
            'product' => $product->name,
            'rows' => $rows,
            'before' => $this->money($before),
            'after' => $this->money($after),
            'pending' => $pending,
            'glass_missing' => $glassMissing,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, changed: int}
     */
    public function apply(int $orderId, array $input): array
    {
        $resolved = $this->resolve($orderId, $input);
        $product = $resolved['product'];
        $items = $resolved['items'];

        if ($resolved['errors'] !== [] || $product === null) {
            return ['errors' => $resolved['errors'], 'changed' => 0];
        }
        $thickness = $product->glass?->thickness_mm;

        /** @var list<string> $from */
        $from = [];

        $changed = DB::transaction(function () use ($orderId, $product, $items, $thickness, &$from): int {
            $changed = 0;

            foreach ($items as $item) {
                if ($item->product_id === (int) $product->getKey()) {
                    continue;
                }

                $from[] = $item->name;
                $result = $this->items->savePane(
                    $orderId,
                    $this->input($item, $product, $thickness),
                    (int) $item->getKey(),
                );

                if ($result['errors'] !== []) {
                    // Formatka, ktora zapisala sie wczesniej, przeszla
                    // te sama walidacje — blad tutaj to blad danych,
                    // nie formularza. Cala zamiana sie wycofuje.
                    throw new \RuntimeException('Zamiana materiału odrzucona: ' . (string) json_encode($result['errors']));
                }

                $changed++;
            }

            return $changed;
        });

        if ($changed > 0) {
            $this->audit->write(
                Order::class,
                $orderId,
                [[
                    'field' => 'materiał formatek',
                    'before' => implode(', ', array_values(array_unique($from))),
                    'after' => sprintf('%s (%d formatek)', $product->name, $changed),
                ]],
                'material_swapped',
            );
        }

        return ['errors' => [], 'changed' => $changed];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *     errors: array<string, list<string>>,
     *     order: Order|null,
     *     product: Product|null,
     *     items: list<OrderItem>
     * }
     */
    private function resolve(int $orderId, array $input): array
    {
        $validator = Validator::make($input, [
            'product_id' => ['required', 'integer'],
            'item_ids' => ['required', 'array', 'min:1', 'max:' . self::MAX_ITEMS],
            'item_ids.*' => ['integer'],
        ], [
            'product_id.required' => 'Wskaż nowy materiał.',
            'item_ids.required' => 'Zaznacz formatki do zamiany.',
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return ['errors' => $messages, 'order' => null, 'product' => null, 'items' => []];
        }

        /** @var Order $order */
        $order = Order::query()->with('contractor')->findOrFail($orderId);

        /** @var Product|null $product */
        $product = Product::query()
            ->with('glass')
            ->where('section', Section::GLASS->value)
            ->where('is_active', true)
            ->find((int) $input['product_id']);

        if ($product === null) {
            return [
                'errors' => ['product_id' => ['Taki materiał nie jest w cenniku szkła.']],
                'order' => null,
                'product' => null,
                'items' => [],
            ];
        }

        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map('intval', (array) $input['item_ids'])));

        /** @var list<OrderItem> $items */
        $items = OrderItem::query()
            ->with(['pane', 'processes.process'])
            ->where('section', Section::GLASS->value)
            ->whereHas('list', static fn($query) => $query->where('order_id', $orderId))
            ->whereIn('id', $ids)
            ->orderBy('order_list_id')
            ->orderBy('position')
            ->get()
            ->all();

        if (count($items) !== count($ids)) {
            return [
                'errors' => ['item_ids' => ['Część zaznaczonych pozycji nie jest formatką tego zlecenia.']],
                'order' => null,
                'product' => null,
                'items' => [],
            ];
        }

        return ['errors' => [], 'order' => $order, 'product' => $product, 'items' => $items];
    }

    /**
     * Formularz formatki odtworzony z zapisu — zmienia się tylko
     * materiał i pozycje etapów.
     *
     * @return array<string, mixed>
     */
    private function input(OrderItem $item, Product $product, ?float $thickness): array
    {
        $pane = $item->pane;

        return [
            'order_list_id' => $item->order_list_id,
            'product_id' => (int) $product->getKey(),
            'width_mm' => $pane?->width_mm,
            'height_mm' => $pane?->height_mm,
            'quantity' => (int) $item->quantity,
            'shape' => $pane?->shape->value,
            'is_tempered' => $pane->is_tempered ?? false,
            'needs_mark' => $pane->needs_mark ?? false,
            'is_urgent' => $item->is_urgent,
            'min_billable_m2' => $pane?->min_billable_m2,
            'note' => $item->note,
            'production_note' => $item->production_note,
            'processes' => $this->selections($item, $thickness),
        ];
    }

    private function specification(OrderItem $item): PaneSpecification
    {
        $pane = $item->pane;

        return new PaneSpecification(
            widthMm: $pane->width_mm ?? 0,
            heightMm: $pane->height_mm ?? 0,
            quantity: max(1, (int) $item->quantity),
            isIrregularShape: $pane?->shape->hasShapeSurcharge() ?? false,
            isTempered: $pane->is_tempered ?? false,
            isUrgent: $item->is_urgent,
            minBillableM2: $pane?->min_billable_m2,
        );
    }

    /**
     * Etapy formatki przepisane na nową grubość.
     *
     * @return list<array<string, mixed>>
     */
    private function selections(OrderItem $item, ?float $thickness): array
    {
        $rows = [];

        foreach ($item->processes->sortBy('position') as $entry) {
            $process = $entry->process;

            if (!$process instanceof Process) {
                continue;
            }

            $rows[] = [
                'process_id' => (int) $process->getKey(),
                'product_id' => $this->match($this->pricing->candidates($process, $thickness), $entry),
                'days' => $entry->days,
                'comment' => $entry->comment,
                // Stara stawka byla dla innej grubosci — cena z cennika.
                'unit_net_price' => null,
            ];
        }

        return $rows;
    }

    /**
     * @param list<Product> $candidates
     */
    private function match(array $candidates, OrderItemProcess $entry): ?int
    {
        foreach ($candidates as $candidate) {
            if ((int) $candidate->getKey() === $entry->product_id) {
                return (int) $candidate->getKey();
            }
        }

        $name = mb_strtolower(trim((string) $entry->parameter));

        if ($name !== '') {
            foreach ($candidates as $candidate) {
                if (mb_strtolower(trim($candidate->name)) === $name) {
                    return (int) $candidate->getKey();
                }
            }
        }

        return count($candidates) === 1 ? (int) $candidates[0]->getKey() : null;
    }

    private function total(OrderItem $item): float
    {
        $total = (float) $item->amount;

        foreach ($item->processes as $entry) {
            $total += (float) $entry->amount;
        }

        return $total;
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }
}
