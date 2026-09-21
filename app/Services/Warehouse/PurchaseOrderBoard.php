<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Enum\PurchaseOrderStatus;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Odczyt zamówień do dostawców.
 *
 * Jedna lista z filtrem statusu, a nie trzy zakładki jak w starym
 * systemie („Lista zamówień", „…otwartych", „…zarchiwizowanych").
 * Były to trzy ekrany różniące się wyłącznie warunkiem `where`.
 */
final readonly class PurchaseOrderBoard
{
    /**
     * @return array<string, mixed>
     */
    public function board(?string $status = null): array
    {
        /** @var iterable<PurchaseOrder> $orders */
        $orders = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->when(
                $status === 'open',
                static fn(Builder $query): Builder => $query->whereIn('status', [
                    PurchaseOrderStatus::DRAFT->value,
                    PurchaseOrderStatus::SENT->value,
                    PurchaseOrderStatus::PARTIAL->value,
                ]),
            )
            ->when(
                $status !== null && $status !== 'open' && $status !== 'all',
                static fn(Builder $query): Builder => $query->where('status', $status),
            )
            ->orderByDesc('number')
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $rows[] = $this->row($order);
        }

        return [
            'rows' => $rows,
            'filters' => $this->filters(),
            'suppliers' => $this->suppliers(),
        ];
    }

    /**
     * Karta zamówienia z pozycjami.
     *
     * @return array<string, mixed>
     */
    public function card(PurchaseOrder $order): array
    {
        $order->loadMissing(['supplier', 'items.product', 'receipts.items']);

        $items = [];

        /** @var iterable<PurchaseOrderItem> $orderItems */
        $orderItems = $order->items;

        foreach ($orderItems as $item) {
            $items[] = [
                'id' => (int) $item->getKey(),
                'product_id' => (int) $item->product_id,
                // Bez `?->`: `purchase_order_items.product_id` jest NOT NULL
                // z `restrictOnDelete`, wiec pozycja bez produktu nie
                // istnieje. Larastan czyta to z migracji i ma racje.
                'code' => $item->product->code,
                'name' => $item->product->name,
                'ordered' => (float) $item->quantity_ordered,
                'received' => (float) $item->quantity_received,
                'outstanding' => $item->outstanding(),
                'unit_net_price' => $item->unit_net_price,
            ];
        }

        $receipts = [];

        foreach ($order->receipts as $receipt) {
            $receipts[] = [
                'id' => (int) $receipt->getKey(),
                'received_at' => $receipt->received_at->toDateString(),
                'document' => $receipt->document,
                'note' => $receipt->note,
                'lines' => $receipt->items->count(),
            ];
        }

        return [...$this->row($order), 'items' => $items, 'receipts' => $receipts];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PurchaseOrder $order): array
    {
        $ordered = 0.0;
        $received = 0.0;
        $value = 0.0;
        $lines = 0;
        $priced = true;

        /** @var iterable<PurchaseOrderItem> $items */
        $items = $order->items;

        foreach ($items as $item) {
            $lines++;
            $ordered += (float) $item->quantity_ordered;
            $received += (float) $item->quantity_received;

            if ($item->unit_net_price === null) {
                // Wartosc bez ceny to nie zero — to wartosc nieznana.
                $priced = false;

                continue;
            }

            $value += (float) $item->quantity_ordered * (float) $item->unit_net_price;
        }

        return [
            'id' => (int) $order->getKey(),
            'number' => $order->number,
            // To samo: `purchase_orders.supplier_id` jest NOT NULL.
            'supplier' => $order->supplier->name,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'is_open' => $order->status->isOpen(),
            'is_editable' => $order->status->isEditable(),
            'ordered_at' => $order->ordered_at?->toDateString(),
            'expected_at' => $order->expected_at?->toDateString(),
            'note' => $order->note,
            'lines' => $lines,
            'quantity_ordered' => round($ordered, 3),
            'quantity_received' => round($received, 3),
            // Brak ceny przy choc jednej pozycji unieważnia sume: kwota
            // policzona z czesci pozycji klamie bardziej niz jej brak.
            'net_value' => $priced ? number_format($value, 2, '.', '') : null,
        ];
    }

    /**
     * @return list<array{code: string, name: string, count: int}>
     */
    private function filters(): array
    {
        /** @var array<string, int> $counts */
        $counts = PurchaseOrder::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $open = 0;

        foreach (PurchaseOrderStatus::cases() as $case) {
            if ($case->isOpen()) {
                $open += (int) ($counts[$case->value] ?? 0);
            }
        }

        $filters = [
            ['code' => 'open', 'name' => 'Otwarte', 'count' => $open],
        ];

        foreach (PurchaseOrderStatus::cases() as $case) {
            $filters[] = [
                'code' => $case->value,
                'name' => $case->label(),
                'count' => (int) ($counts[$case->value] ?? 0),
            ];
        }

        $filters[] = ['code' => 'all', 'name' => 'Wszystkie', 'count' => array_sum($counts)];

        return $filters;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function suppliers(): array
    {
        /** @var iterable<Supplier> $suppliers */
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($suppliers as $supplier) {
            $rows[] = ['id' => (int) $supplier->getKey(), 'name' => $supplier->name];
        }

        return $rows;
    }
}
