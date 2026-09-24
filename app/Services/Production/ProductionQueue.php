<?php

declare(strict_types=1);

namespace App\Services\Production;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Order;
use App\Models\Workstation;
use App\Enum\ProductionStatus;
use App\Models\ProductionTask;
use App\Models\OrderDrawing;
use App\Services\Orders\OrderProgress;

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
    public function __construct(
        private OrderProgress $progress = new OrderProgress(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(
        ?int $workstationId = null,
        bool $unassignedOnly = false,
        ?int $processId = null,
        bool $includeDone = false,
        ?Carbon $today = null,
        int $perPage = 50,
        int $page = 1,
    ): array {
        $day = ($today ?? Carbon::today())->startOfDay();
        $date = $day->toDateString();
        $perPage = max(1, $perPage);

        // Zbior, o ktorym mowi ekran — jeden dla wierszy, stron
        // i licznikow w naglowku. Liczniki liczone z pokazanych wierszy
        // przestaja byc prawda w chwili, gdy lista ma strony.
        $scope = fn(): Builder => $this->queue($includeDone)
            ->when(
                $workstationId !== null,
                static fn(Builder $builder): Builder => $builder->where('production_tasks.workstation_id', $workstationId),
            )
            ->when(
                $unassignedOnly,
                static fn(Builder $builder): Builder => $builder->whereNull('production_tasks.workstation_id'),
            )
            ->when(
                $processId !== null,
                static fn(Builder $builder): Builder => $builder->where('production_tasks.process_id', $processId),
            );

        $total = $scope()->count();
        $pages = max(1, (int) ceil($total / $perPage));
        // Po odhaczeniu ostatniego etapu na ostatniej stronie ta strona
        // znika. Ekran ma wtedy pokazac poprzednia, a nie pusta liste
        // z napisem „nic nie czeka" nad kolejka, ktora czeka.
        $page = min(max(1, $page), $pages);

        /** @var \Illuminate\Database\Eloquent\Collection<int, ProductionTask> $tasks */
        $tasks = $this->byUrgency($scope())
            ->with([
                'process',
                'workstation',
                'doneBy',
                'item.pane',
                'item.list',
                'order.contractor',
            ])
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        $orderIds = $tasks->pluck('order_id')->unique()->all();

        // Liczba rysunkow jednym zapytaniem dla calej strony. Liczona
        // w kazdym wierszu osobno dawala jedno zapytanie na etap —
        // przy duzej hali kilkanascie tysiecy na jedno otwarcie ekranu.
        $drawings = $this->drawingCounts($orderIds);
        $progress = $this->progress->production($orderIds);

        $rows = [];

        foreach ($tasks as $task) {
            $rows[] = $this->row($task, $day, $drawings, $progress);
        }

        return [
            'workstations' => $this->workstations(),
            'rows' => $rows,
            'summary' => [
                'shown' => count($rows),
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'per_page' => $perPage,
                'problems' => $scope()
                    ->where('production_tasks.status', ProductionStatus::PROBLEM->value)
                    ->count(),
                'overdue' => $scope()
                    ->whereHas(
                        'order',
                        static fn(Builder $order): Builder => $order->whereRaw(
                            'COALESCE(shifted_deadline, client_deadline) < ?',
                            [$date],
                        ),
                    )
                    ->count(),
                'as_of' => $date,
            ],
        ];
    }

    /**
     * Kolejność pilności w bazie, nie w pamięci — inaczej strona druga
     * byłaby „następne etapy po id", a nie następne najpilniejsze.
     *
     * Pilna pozycja idzie przed terminem, bo po to się ją zaznacza: to
     * jedyny sposób, żeby człowiek przestawił kolejność, której data sama
     * nie przestawi. Wewnątrz pilnych nadal rządzi termin; bez terminu —
     * na końcu. Numer zlecenia i pozycja w marszrucie rozstrzygają remis,
     * a id etapu czyni kolejność jednoznaczną między stronami.
     *
     * @param Builder<ProductionTask> $builder
     * @return Builder<ProductionTask>
     */
    private function byUrgency(Builder $builder): Builder
    {
        $deadline = 'COALESCE(orders.shifted_deadline, orders.client_deadline)';

        return $builder
            ->select('production_tasks.*')
            ->join('order_items', 'order_items.id', '=', 'production_tasks.order_item_id')
            ->join('orders', 'orders.id', '=', 'production_tasks.order_id')
            ->orderByDesc('order_items.is_urgent')
            ->orderByRaw("CASE WHEN {$deadline} IS NULL THEN 1 ELSE 0 END")
            ->orderByRaw($deadline)
            ->orderBy('orders.number')
            ->orderBy('production_tasks.position')
            ->orderBy('production_tasks.id');
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
     * @param array<int, int> $drawings liczba rysunków na zlecenie
     * @param array<int, array{done: int, total: int, percent: int}> $progress postęp zleceń
     * @return array<string, mixed>
     */
    private function row(ProductionTask $task, Carbon $day, array $drawings, array $progress): array
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
            // Usluga nie ma formatki — wtedy nie ma i ksztaltu.
            'shape' => $pane?->shape->value,
            // Parametr to powod, dla ktorego operator idzie zapytac do
            // biura. Ma stac przy pozycji, nie w zakladce obok.
            'parameter' => $task->parameter,
            'comment' => $order->production_comment,
            'list_comment' => $item->list->comment,
            // Instrukcja przy tej jednej formatce — najkonkretniejsza
            // z trzech, wiec operator ma ja widziec razem z pozostalymi.
            'item_note' => $item->production_note,
            'is_urgent' => (bool) $item->is_urgent,
            'drawings' => $drawings[(int) $task->order_id] ?? 0,
            // Jak daleko jest cale zlecenie, nie ten jeden etap —
            // operator widzi, czy to ostatnia rzecz przed wydaniem.
            // Bez kwot: wplaty na hali nie maja czego szukac.
            'order_progress' => $progress[(int) $task->order_id] ?? null,
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
        $counts = $this->queue()
            ->selectRaw('workstation_id, COUNT(*) as open')
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
        $unassigned = $this->queue()
            ->whereNull('workstation_id')
            ->count();

        if ($unassigned > 0) {
            $rows[] = ['id' => null, 'name' => null, 'open' => $unassigned];
        }

        return $rows;
    }

    /**
     * Ile etapów czeka — ta sama liczba, którą pokazuje ekran kolejki.
     *
     * Pulpit budował całą kolejkę, żeby odczytać z niej jedną liczbę:
     * przy dużej hali to kilkanaście tysięcy wierszy dla kafelka.
     * Liczone z tego samego zapytania bazowego, więc kafelek i ekran
     * nie mogą się rozjechać.
     */
    public function count(): int
    {
        return $this->queue()->count();
    }

    /**
     * @param array<int|string, mixed> $orderIds
     * @return array<int, int>
     */
    private function drawingCounts(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = OrderDrawing::query()
            ->selectRaw('order_id, COUNT(*) as total')
            ->whereIn('order_id', array_values($orderIds))
            ->groupBy('order_id')
            ->pluck('total', 'order_id')
            ->map(static fn(mixed $total): int => (int) $total)
            ->all();

        return $counts;
    }

    /**
     * Zbiór, o którym mówi kolejka — **jeden dla wierszy i dla liczników
     * przy zakładkach**.
     *
     * Liczniki liczyły osobnym zapytaniem, bez warunku o etapach
     * podzlecanych. Zakładka „Bez stanowiska 9" stała nad listą sześciu
     * wierszy: trzy etapy hartowania siedziały w liczniku i nigdy nie
     * pojawiały się na ekranie. Dwa zapytania o ten sam zbiór rozjadą się
     * przy pierwszym nowym warunku, a taki rozjazd nie zgłasza się sam.
     *
     * @return Builder<ProductionTask>
     */
    private function queue(bool $includeDone = false): Builder
    {
        return ProductionTask::query()
            ->when(
                !$includeDone,
                static fn(Builder $builder): Builder => $builder->where(
                    'production_tasks.status',
                    '!=',
                    ProductionStatus::DONE->value,
                ),
            )
            // Etapu podzlecanego hala nie odhacza: praca dzieje sie
            // gdzie indziej, a zadanie zamyka powrot od podwykonawcy.
            // Zadanie nadal istnieje i liczy sie do `allDone()`, wiec
            // zlecenie nie przejdzie na „Gotowe" przed powrotem szkla.
            ->whereHas(
                'process',
                static fn(Builder $builder): Builder => $builder->where('is_subcontracted', false),
            );
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
