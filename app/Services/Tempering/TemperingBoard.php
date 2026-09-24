<?php

declare(strict_types=1);

namespace App\Services\Tempering;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vehicle;
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
        $items = $this->waiting()
            ->with(['item.pane', 'item.product.glass', 'item.list.order.contractor'])
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

        // Pilne wygrywa z terminem — ta sama regula, co w kolejce hali.
        // Po to sie pilne zaznacza: to jedyny sposob, zeby czlowiek
        // przestawil kolejnosc, ktorej data sama nie przestawi.
        // Brak terminu idzie na koniec, a nie na poczatek.
        usort($rows, static function (array $a, array $b): int {
            if ($a['is_urgent'] !== $b['is_urgent']) {
                return $a['is_urgent'] ? -1 : 1;
            }

            return [$a['deadline'] ?? '9999-12-31', $a['order_number']]
                <=> [$b['deadline'] ?? '9999-12-31', $b['order_number']];
        });

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
     * Ile pozycji czeka na piec — ta sama liczba co „shown" w kolejce.
     *
     * Pulpit budował całą kolejkę z wagą i metrażem każdej szyby, żeby
     * odczytać z niej jedną liczbę. Liczone z tego samego zapytania
     * bazowego, więc kafelek i ekran hartowni nie mogą się rozjechać.
     */
    public function count(): int
    {
        return $this->waiting()->count();
    }

    /**
     * Zbiór kolejki: w kolejce, bez partii, **z formatką**.
     *
     * Warunek o formatce stał dotąd tylko w pętli (`row()` zwracał
     * `null`), więc licznik z samego zapytania liczyłby pozycje, których
     * ekran nigdy nie pokaże. Teraz stoi w zapytaniu i obowiązuje oba.
     *
     * @return Builder<TemperingItem>
     */
    private function waiting(): Builder
    {
        return TemperingItem::query()
            ->where('status', TemperingItemStatus::QUEUED->value)
            ->whereNull('tempering_batch_id')
            ->whereHas('item.pane');
    }

    /**
     * Stan hartowania jednego zlecenia — dla karty zlecenia.
     *
     * Dane o partii istniały od początku, ale tylko w jedną stronę:
     * hartownia wiedziała o zleceniu, zlecenie o hartowni nie. To jest
     * to brakujące przejście. `null` znaczy, że zlecenie nie ma nic do
     * hartowania — wtedy karta o tym nie wspomina.
     *
     * @return array<string, mixed>|null
     */
    public function forOrder(Order $order): ?array
    {
        /** @var list<int> $itemIds */
        $itemIds = OrderItem::query()
            ->whereHas('list', static fn(Builder $query): Builder => $query
                ->where('order_id', $order->getKey()))
            ->pluck('id')
            ->all();

        if ($itemIds === []) {
            return null;
        }

        /** @var iterable<TemperingItem> $items */
        $items = TemperingItem::query()
            ->with('batch.supplier')
            ->whereIn('order_item_id', $itemIds)
            ->get();

        $counts = ['queued' => 0.0, 'sent' => 0.0, 'returned' => 0.0, 'broken' => 0.0];
        $batches = [];
        $longest = null;
        $found = 0;

        foreach ($items as $item) {
            $found++;
            $quantity = (float) $item->quantity;

            $counts[match ($item->status) {
                TemperingItemStatus::QUEUED => 'queued',
                TemperingItemStatus::SENT => 'sent',
                TemperingItemStatus::BROKEN, TemperingItemStatus::MISSING => 'broken',
                default => 'returned',
            }] += $quantity;

            $batch = $item->batch;

            if ($batch === null || $item->status !== TemperingItemStatus::SENT) {
                continue;
            }

            $days = $batch->daysOut();
            $batches[(int) $batch->getKey()] = [
                'id' => (int) $batch->getKey(),
                'number' => $batch->number,
                'supplier' => $batch->supplier->name,
                'sent_at' => $batch->sent_at?->toDateString(),
                'expected_at' => $batch->expected_at?->toDateString(),
                'days_out' => $days,
            ];

            if ($days !== null && ($longest === null || $days > $longest)) {
                $longest = $days;
            }
        }

        // Zlecenie bez ani jednej pozycji do hartowania nie ma o czym
        // mowic — karta wtedy o hartowni nie wspomina.
        if ($found === 0) {
            return null;
        }

        return [
            // Czeka — czyli zlecenie nie przejdzie na „Gotowe".
            'is_waiting' => $counts['queued'] > 0 || $counts['sent'] > 0,
            'queued' => round($counts['queued'], 3),
            'sent' => round($counts['sent'], 3),
            'returned' => round($counts['returned'], 3),
            'broken' => round($counts['broken'], 3),
            'days_out' => $longest,
            'batches' => array_values($batches),
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
            // Waga partii liczy sie z pozycji, wiec lista potrzebuje
            // tego samego, co karta — inaczej kazdy wiersz dociagalby
            // formatki osobnym zapytaniem.
            ->with([
                'supplier',
                'vehicle',
                'items.item.pane',
                'items.item.product.glass',
                'items.item.list.order.contractor',
            ])
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
            'vehicles' => $this->vehicles(),
        ];
    }

    /**
     * Karta partii z pozycjami.
     *
     * @return array<string, mixed>
     */
    public function card(TemperingBatch $batch): array
    {
        $batch->loadMissing([
            'supplier',
            'vehicle',
            'items.item.pane',
            'items.item.product.glass',
            'items.item.list.order.contractor',
        ]);

        $items = [];
        $totalM2 = 0.0;

        foreach ($batch->items as $position) {
            $row = $this->row($position);

            if ($row !== null) {
                $items[] = $row;
                $totalM2 += $row['m2'];
            }
        }

        // Koszt partii rozksiegowany po m2 — bo tak rozlicza sie
        // podwykonawca. To **koszt**, nie cena: klient placi za
        // hartowanie z cennika procesu H (H-08). Ta liczba sluzy do
        // porownania jednego z drugim, a nie do wyceny.
        $cost = $batch->net_cost === null ? null : (float) $batch->net_cost;

        if ($cost !== null && $totalM2 > 0) {
            foreach ($items as $index => $row) {
                $items[$index]['cost_share'] = number_format(
                    $cost * ($row['m2'] / $totalM2),
                    2,
                    '.',
                    '',
                );
            }
        }

        return [
            ...$this->batchRow($batch),
            'items' => $items,
            'm2' => round($totalM2, 3),
        ];
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
            // Termin i pilne, bo bez nich „dobieranie do samochodu"
            // jest zgadywaniem: widac kilogramy, nie widac, co sie pali.
            'is_urgent' => (bool) $item->is_urgent,
            'deadline' => $order->effectiveDeadline()?->toDateString(),
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
        $kg = 0.0;

        foreach ($batch->items as $position) {
            $lines++;
            $quantity += (float) $position->quantity;

            $row = $this->row($position);

            if ($row !== null) {
                $kg += $row['kg'];
            }

            if (!$position->status->covers()) {
                $broken++;
            }
        }

        $vehicle = $batch->vehicle;
        $payload = $vehicle === null ? null : $vehicle->payload_kg;

        return [
            'id' => (int) $batch->getKey(),
            'number' => $batch->number,
            'supplier' => $batch->supplier->name,
            'vehicle' => $vehicle?->name,
            'vehicle_id' => $batch->vehicle_id,
            'payload_kg' => $payload,
            'load_kg' => round($kg, 2),
            // Zapelnienie i nadwyzka licza sie tylko przy wskazanym
            // aucie. Bez auta nie ma wobec czego wazyc — i nie ma
            // powodu pokazywac stu procent z niczego.
            'load_percent' => $payload === null || $payload <= 0
                ? null
                : (int) round($kg / $payload * 100),
            'over_by_kg' => $payload === null || $kg <= $payload
                ? null
                : round($kg - $payload, 2),
            'departure_at' => $batch->departure_at?->toDateString(),
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
     * Flota do wyboru przy kursie. Ładowność idzie razem z nazwą, żeby
     * przy wyborze auta było widać, ile w nie wejdzie.
     *
     * @return list<array{id: int, name: string, payload_kg: int}>
     */
    private function vehicles(): array
    {
        /** @var iterable<Vehicle> $vehicles */
        $vehicles = Vehicle::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($vehicles as $vehicle) {
            $rows[] = [
                'id' => (int) $vehicle->getKey(),
                'name' => $vehicle->name,
                'payload_kg' => $vehicle->payload_kg,
            ];
        }

        return $rows;
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
