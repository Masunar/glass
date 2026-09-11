<?php

declare(strict_types=1);

namespace App\Services\Production;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Workstation;
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use App\Models\OrderDrawing;

/**
 * Kolejka stanowiska — „co mam dziś zrobić".
 *
 * To nie jest siatka dla szefa produkcji, tylko lista dla człowieka
 * przy maszynie. Stąd trzy zasady, od których nie odchodzimy:
 *
 * - **bez cen.** Operator nie ma powodu widzieć kwot, a zlecenie
 *   z widoczną ceną wędruje po hali razem z nią.
 * - **pilność zamiast numeru.** Sortowanie po terminie zlecenia, nie
 *   po numerze: hala ma robić to, co się pali, a nie to, co przyszło
 *   pierwsze.
 * - **parametr przy pozycji.** RAL, faza i rodzaj folii są tym, po co
 *   operator dziś wstaje i idzie do biura zapytać.
 *
 * Etapów nie sortujemy po marszrucie w poprzek zlecenia, bo tego jeszcze
 * nie umiemy uczciwie: bez zmierzonych czasów operacji nie wiemy, czy
 * szlif zdąży po cięciu. Kolejność w marszrucie jest pokazana, ale nie
 * blokuje — operator widzi, że przed nim jest cięcie, i sam decyduje.
 */
final readonly class ProductionQueue
{
    /**
     * @return array<string, mixed>
     */
    public function board(
        ?int $workstationId = null,
        bool $unassignedOnly = false,
        ?int $processId = null,
        bool $includeDone = false,
        ?Carbon $today = null,
    ): array {
        $day = ($today ?? Carbon::today())->startOfDay();

        $query = ProductionTask::query()
            ->with([
                'process',
                'workstation',
                'doneBy',
                'item.pane',
                'item.list',
                'order.contractor',
            ])
            ->when($workstationId !== null, static fn($builder) => $builder->where('workstation_id', $workstationId))
            ->when($unassignedOnly, static fn($builder) => $builder->whereNull('workstation_id'))
            ->when($processId !== null, static fn($builder) => $builder->where('process_id', $processId))
            ->when(
                !$includeDone,
                static fn($builder) => $builder->where('status', '!=', ProductionStatus::DONE->value),
            );

        /** @var iterable<ProductionTask> $tasks */
        $tasks = $query->get();

        $rows = [];

        foreach ($tasks as $task) {
            $rows[] = $this->row($task, $day);
        }

        // Sortowanie po pilnosci robimy w pamieci, bo termin zlecenia to
        // "przesuniety albo klienta" - warunek, ktorego nie da sie zapisac
        // jednym indeksem, a liczba zadan w kolejce jest rzedu setek.
        usort($rows, static function (array $a, array $b): int {
            $left = $a['days_left'] ?? PHP_INT_MAX;
            $right = $b['days_left'] ?? PHP_INT_MAX;

            return [$left, $a['order_number'], $a['position']]
                <=> [$right, $b['order_number'], $b['position']];
        });

        return [
            'workstations' => $this->workstations(),
            'rows' => $rows,
            'summary' => [
                'shown' => count($rows),
                'problems' => count(array_filter(
                    $rows,
                    static fn(array $row): bool => $row['status'] === ProductionStatus::PROBLEM->value,
                )),
                'overdue' => count(array_filter(
                    $rows,
                    static fn(array $row): bool => $row['days_left'] !== null && $row['days_left'] < 0,
                )),
                'as_of' => $day->toDateString(),
            ],
        ];
    }

    /**
     * Stan etapów jednego zlecenia, po procesach — tym karta zlecenia
     * wypełnia ścieżkę produkcji, która do tej pory pokazywała same
     * nazwy kroków bez informacji, czy którykolwiek jest zrobiony.
     *
     * @return array<int, array{done: int, total: int, problems: int}>
     */
    public function progressByProcess(Order $order): array
    {
        /** @var iterable<ProductionTask> $tasks */
        $tasks = ProductionTask::query()
            ->where('order_id', (int) $order->getKey())
            ->get(['process_id', 'status']);

        $progress = [];

        foreach ($tasks as $task) {
            $id = (int) $task->process_id;
            $progress[$id] ??= ['done' => 0, 'total' => 0, 'problems' => 0];

            $progress[$id]['total']++;

            if ($task->status === ProductionStatus::DONE) {
                $progress[$id]['done']++;
            }

            if ($task->status === ProductionStatus::PROBLEM) {
                $progress[$id]['problems']++;
            }
        }

        return $progress;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ProductionTask $task, Carbon $day): array
    {
        // Zlecenie, pozycja i proces sa wymagane kluczem obcym, wiec
        // zawsze sa. Nullowalne zostaja szyba (usluga jej nie ma)
        // i stanowisko (slownik procesow miewa te kolumne pusta).
        $item = $task->item;
        $pane = $item->pane;
        $order = $task->order;
        $deadline = $order->effectiveDeadline();

        return [
            'id' => (int) $task->getKey(),
            'order_id' => (int) $task->order_id,
            'order_number' => (int) $order->number,
            'contractor' => $order->contractor?->displayName(),
            'deadline' => $deadline?->toDateString(),
            'days_left' => $deadline === null ? null : (int) $day->diffInDays($deadline, false),
            'process' => $task->process->name,
            'process_code' => $task->process->code,
            'workstation' => $task->workstation?->name,
            'position' => (int) $task->position,
            'item' => $item->name,
            'quantity' => $item->quantity,
            'width_mm' => $pane?->width_mm,
            'height_mm' => $pane?->height_mm,
            'is_irregular_shape' => (bool) ($pane->is_irregular_shape ?? false),
            // Parametr to powod, dla ktorego operator idzie zapytac do
            // biura. Ma stac przy pozycji, nie w zakladce obok.
            'parameter' => $task->parameter,
            'comment' => $order->production_comment,
            'list_comment' => $item->list->comment,
            'drawings' => OrderDrawing::query()->where('order_id', $task->order_id)->count(),
            'status' => $task->status->value,
            'issue_type' => $task->issue_type?->value,
            'note' => $task->note,
            'started_at' => $task->started_at?->format('d.m.Y H:i'),
            'finished_at' => $task->finished_at?->format('d.m.Y H:i'),
            'minutes_spent' => $task->minutes_spent,
            'by' => $this->name($task),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workstations(): array
    {
        /** @var array<int, int> $counts */
        $counts = ProductionTask::query()
            ->selectRaw('workstation_id, COUNT(*) as open')
            ->where('status', '!=', ProductionStatus::DONE->value)
            ->groupBy('workstation_id')
            ->pluck('open', 'workstation_id')
            ->all();

        $rows = [];

        /** @var iterable<Workstation> $workstations */
        $workstations = Workstation::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        foreach ($workstations as $workstation) {
            $id = (int) $workstation->getKey();

            $rows[] = [
                'id' => $id,
                'name' => $workstation->name,
                'open' => (int) ($counts[$id] ?? 0),
            ];
        }

        // Etapy bez stanowiska nie moga zniknac z ekranu. Slownik
        // procesow ma `workstation_id` puste tam, gdzie nikt go nie
        // uzupelnil - i to wlasnie te etapy trzeba zobaczyc.
        $unassigned = ProductionTask::query()
            ->whereNull('workstation_id')
            ->where('status', '!=', ProductionStatus::DONE->value)
            ->count();

        if ($unassigned > 0) {
            $rows[] = ['id' => null, 'name' => null, 'open' => $unassigned];
        }

        return $rows;
    }

    private function name(ProductionTask $task): ?string
    {
        $user = $task->doneBy;

        if ($user === null) {
            return null;
        }

        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? null : $name;
    }
}
