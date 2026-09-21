<?php

declare(strict_types=1);

namespace App\Services\Tempering;

use App\Models\Supplier;
use App\Models\TemperingItem;
use App\Models\TemperingBatch;
use App\Enum\TemperingItemStatus;
use App\Enum\TemperingBatchStatus;
use App\DTO\Pricing\PaneSpecification;
use Illuminate\Database\Eloquent\Builder;

/**
 * Odczyt hartowni: kolejka i partie.
 *
 * To jedyny ekran w aplikacji zorganizowany **w poprzek zleceń** —
 * wokół szkła, nie wokół klienta. Operator kompletuje wsad, więc liczy
 * kilogramy i metry, a numer zlecenia jest tylko kontekstem.
 */
final readonly class TemperingBoard
{
    /**
     * Kolejka: pozycje czekające na wysłanie.
     *
     * @return array<string, mixed>
     */
    public function queue(?float $thickness = null): array
    {
        /** @var iterable<TemperingItem> $items */
        $items = TemperingItem::query()
            ->with(['item.pane', 'item.product.glass', 'item.list.order.contractor'])
            ->where('status', TemperingItemStatus::QUEUED->value)
            ->whereNull('tempering_batch_id')
            ->orderBy('id')
            ->get();

        $rows = [];
        $thicknesses = [];

        foreach ($items as $position) {
            $row = $this->row($position);

            if ($row === null) {
                continue;
            }

            if ($row['thickness_mm'] !== null) {
                $thicknesses[(string) $row['thickness_mm']] = true;
            }

            if ($thickness !== null && $row['thickness_mm'] !== $thickness) {
                continue;
            }

            $rows[] = $row;
        }

        // Grubosci sortujemy liczbowo, zeby 10 nie stalo przed 4.
        $available = array_map(floatval(...), array_keys($thicknesses));
        sort($available);

        return [
            'rows' => $rows,
            'thicknesses' => $available,
            'summary' => [
                'shown' => count($rows),
                'kg' => round(array_sum(array_column($rows, 'kg')), 2),
                'm2' => round(array_sum(array_column($rows, 'm2')), 3),
            ],
        ];
    }

    /**
     * Partie z filtrem statusu.
     *
     * @return array<string, mixed>
     */
    public function batches(?string $status = null): array
    {
        /** @var iterable<TemperingBatch> $batches */
        $batches = TemperingBatch::query()
            ->with(['supplier', 'items'])
            ->when(
                $status === 'open',
                static fn(Builder $query): Builder => $query->whereIn('status', [
                    TemperingBatchStatus::DRAFT->value,
                    TemperingBatchStatus::SENT->value,
                    TemperingBatchStatus::RETURNED->value,
                ]),
            )
            ->when(
                $status !== null && $status !== 'open' && $status !== 'all',
                static fn(Builder $query): Builder => $query->where('status', $status),
            )
            ->orderByDesc('number')
            ->get();

        $rows = [];

        foreach ($batches as $batch) {
            $rows[] = $this->batchRow($batch);
        }

        return [
            'rows' => $rows,
            'filters' => $this->filters(),
            'suppliers' => $this->suppliers(),
        ];
    }

    /**
     * Karta partii z pozycjami.
     *
     * @return array<string, mixed>
     */
    public function card(TemperingBatch $batch): array
    {
        $batch->loadMissing(['supplier', 'items.item.pane', 'items.item.product.glass', 'items.item.list.order.contractor']);

        $items = [];

        foreach ($batch->items as $position) {
            $row = $this->row($position);

            if ($row !== null) {
                $items[] = $row;
            }
        }

        return [...$this->batchRow($batch), 'items' => $items];
    }

    /**
     * Wiersz pozycji: szkło, wymiary, waga, powierzchnia.
     *
     * Waga i powierzchnia idą przez te same pomocnicze metody, co
     * wycena i dostawy (`ProductGlass::weightOfPane`,
     * `PaneSpecification::squareMeters`). Stary system liczył je
     * osobno w każdym module i dawał trzy różne wyniki.
     *
     * @return array<string, mixed>|null
     */
    private function row(TemperingItem $position): ?array
    {
        // `tempering_items.order_item_id` jest NOT NULL z kaskada, wiec
        // pozycja bez formatki nie istnieje. `order_panes` to za to
        // rozszerzenie 1:1, ktorego dla pozycji nieszklanej nie ma —
        // i tego sprawdzic trzeba.
        $item = $position->item;
        $pane = $item->pane;

        if ($pane === null) {
            return null;
        }

        $quantity = (float) $position->quantity;
        $glass = $item->product?->glass;

        $specification = new PaneSpecification(
            widthMm: $pane->width_mm,
            heightMm: $pane->height_mm,
            quantity: (int) ceil($quantity),
            isIrregularShape: $pane->is_irregular_shape,
            isTempered: $pane->is_tempered,
        );

        // `order_items.order_list_id` i `order_lists.order_id` sa NOT NULL
        // z kaskada, wiec pozycja bez listy i lista bez zlecenia nie
        // istnieja. Kontrahent juz tak — jego kolumna jest nullowalna.
        $order = $item->list->order;

        return [
            'id' => (int) $position->getKey(),
            'order_id' => (int) $order->getKey(),
            'order_number' => $order->number,
            'contractor' => $order->contractor?->displayName(),
            'name' => $item->name,
            'thickness_mm' => $glass?->thickness_mm,
            'width_mm' => $pane->width_mm,
            'height_mm' => $pane->height_mm,
            'quantity' => $quantity,
            'kg' => $glass?->weightOfPane($pane->width_mm, $pane->height_mm, (int) ceil($quantity)) ?? 0.0,
            'm2' => round($specification->squareMeters(), 3),
            'is_irregular_shape' => $pane->is_irregular_shape,
            // Znak jedzie wprost z formatki. Co fizycznie oznacza —
            // H-01, nadal bez odpowiedzi, wiec zostaje flaga.
            'needs_mark' => $pane->needs_mark,
            'note' => $item->production_note,
            'status' => $position->status->value,
            'status_label' => $position->status->label(),
            'replaces_id' => $position->replaces_id,
            'batch_number' => $position->batch?->number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function batchRow(TemperingBatch $batch): array
    {
        $lines = 0;
        $quantity = 0.0;
        $broken = 0;

        foreach ($batch->items as $position) {
            $lines++;
            $quantity += (float) $position->quantity;

            if (!$position->status->covers()) {
                $broken++;
            }
        }

        return [
            'id' => (int) $batch->getKey(),
            'number' => $batch->number,
            'supplier' => $batch->supplier->name,
            'status' => $batch->status->value,
            'status_label' => $batch->status->label(),
            'is_open' => $batch->status->isOpen(),
            'is_editable' => $batch->status->isEditable(),
            'sent_at' => $batch->sent_at?->toDateString(),
            'expected_at' => $batch->expected_at?->toDateString(),
            'returned_at' => $batch->returned_at?->toDateString(),
            // Jedyna liczba, ktora mowi, czy podwykonawca sie spoznia.
            'days_out' => $batch->daysOut(),
            'net_cost' => $batch->net_cost,
            'document' => $batch->document,
            'note' => $batch->note,
            'lines' => $lines,
            'quantity' => round($quantity, 3),
            'broken' => $broken,
        ];
    }

    /**
     * @return list<array{code: string, name: string, count: int}>
     */
    private function filters(): array
    {
        /** @var array<string, int> $counts */
        $counts = TemperingBatch::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $open = 0;

        foreach (TemperingBatchStatus::cases() as $case) {
            if ($case->isOpen()) {
                $open += (int) ($counts[$case->value] ?? 0);
            }
        }

        $filters = [['code' => 'open', 'name' => 'Otwarte', 'count' => $open]];

        foreach (TemperingBatchStatus::cases() as $case) {
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
