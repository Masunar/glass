<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Status;
use App\Enum\StatusDomain;
use App\Services\Alerts\AlertBoard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lista zleceń — pasma pilności zamiast sortowania.
 *
 * Zamiast układać wiersze po jednej kolumnie, lista dzieli je na to,
 * co wymaga decyzji dzisiaj, i całą resztę: „Dziś", „Zaległe",
 * „Kolejne dni". Zakładki statusów zostają jako filtr, nie jako główny
 * sposób czytania.
 *
 * Termin brany do pasma to **termin przesunięty, jeśli istnieje**.
 * W starym systemie prawdziwy termin siedział w komentarzu
 * („PRODUKCJA: deadline 15.09"), a lista liczyła od innej daty
 * i pokazywała opóźnienie, którego nie było.
 */
final readonly class OrderBoardService
{
    public function __construct(
        private OrderNextStep $nextStep = new OrderNextStep(),
        private OrderValue $value = new OrderValue(),
        private AlertBoard $alerts = new AlertBoard(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(
        ?string $query = null,
        ?string $statusCode = null,
        ?Carbon $today = null,
        int $limit = 200,
        ?int $ownerId = null,
        int $page = 1,
    ): array {
        $day = ($today ?? Carbon::today())->startOfDay();
        $date = $day->toDateString();
        $page = max(1, $page);
        $needle = $query !== null ? trim($query) : '';
        $statusCode = $statusCode === '' ? null : $statusCode;

        // Zbior, o ktorym mowi ekran: zakladka, szukanie, „moje". Z niego
        // biora sie i wiersze, i liczniki — dwa osobne zapytania o ten
        // sam zbior rozjada sie przy pierwszym nowym warunku.
        $scope = fn(): Builder => Order::query()
            ->when(
                $statusCode !== null,
                static fn(Builder $builder): Builder => $builder->whereHas(
                    'status',
                    static fn(Builder $status): Builder => $status->where('code', $statusCode),
                ),
            )
            ->when(
                $needle !== '',
                fn(Builder $builder): Builder => $this->applySearch($builder, $needle),
            )
            // „Moje" znaczy **prowadzacy = ja** i tylko to. Druga
            // definicja tego samego slowa — na przyklad „zalozone przeze
            // mnie" — rozjechalaby sie z pierwszym przekazaniem zlecenia.
            ->when(
                $ownerId !== null,
                static fn(Builder $builder): Builder => $builder->where('owner_id', $ownerId),
            )
            // Bez zakladki i bez szukania lista pokazuje sprawy w toku.
            // Zamkniete — historia, anulowane, odrzucone oferty — maja
            // swoje zakladki i znajduja sie szukaniem. Przy dziesieciu
            // tysiacach zlecen archiwum to wiekszosc bazy.
            ->when(
                $statusCode === null && $needle === '',
                static fn(Builder $builder): Builder => $builder->whereHas(
                    'status',
                    static fn(Builder $status): Builder => $status->where('is_final', false),
                ),
            );

        /** @var Collection<int, Order> $orders */
        $orders = $this->byUrgency($scope(), $date)
            ->with([
                'contractor',
                'status',
                'pickupLocation',
                'owner',
                'invoiceType',
                'discounts',
                'lists.items.processes',
                'payments',
            ])
            ->limit($limit)
            // Kolejne strony ida ta sama kolejnoscia pilnosci — druga
            // strona to nastepne dwiescie spraw, nie nastepne numery.
            ->offset(($page - 1) * $limit)
            ->get();

        // Warunek zaliczki pyta o saldo kontrahenta przy kazdym wierszu.
        // Jedno pobranie dla wszystkich naraz zamiast dwustu osobnych.
        $this->nextStep->balance()->preload(array_values(array_filter(
            $orders->pluck('contractor_id')->unique()->map(
                static fn($id): int => (int) $id,
            )->all(),
            static fn(int $id): bool => $id > 0,
        )));

        // Alerty licza sie dla calej bazy, nie dla pokazanych wierszy:
        // czerwony licznik przy zakladce ma mowic o calosci.
        $alerts = $this->alerts->forOrders($day);

        $bands = ['today' => [], 'overdue' => [], 'later' => []];

        foreach ($orders as $order) {
            $bands[$this->bandFor($order, $day)][] = $this->row($order, $day, $alerts);
        }

        $total = $scope()->count();

        return [
            'bands' => [
                $this->band('today', $bands['today']),
                $this->band('overdue', $bands['overdue']),
                $this->band('later', $bands['later']),
            ],
            'filters' => $this->filters($day, $ownerId),
            'summary' => [
                // Liczone w bazie ta sama regula, co pasmo, a nie
                // z pokazanych wierszy. Symulacja na 10 000 zlecen:
                // pasek „Zalegle 27", a zaleglych bylo 1700.
                'today' => $this->due($scope(), '=', $date),
                'overdue' => $this->due($scope(), '<', $date),
                'shown' => $orders->count(),
                // Ile pasuje do filtra, zanim lista zostala przycieta.
                // Przyciecie bez sygnalu to ta sama cicha awaria, co
                // licznik liczony z pokazanych wierszy.
                'total' => $total,
                'page' => $page,
                'pages' => max(1, (int) ceil($total / max(1, $limit))),
                'per_page' => $limit,
                // Ekran musi wiedziec, ktora liste widzi. Bez tego
                // przelacznik „moje" moglby zostac wcisniety, a lista
                // pokazywac calosc — i nikt by tego nie zauwazyl.
                'mine' => $ownerId !== null,
                'as_of' => $day->toDateString(),
            ],
        ];
    }

    /**
     * Kolejność wyboru wierszy: **najpierw to, co się pali**.
     *
     * Dzisiejsze, potem zaległe od najstarszego terminu, potem kolejne
     * dni, na końcu bez terminu i zamknięte. Wcześniej lista brała
     * dwieście najnowszych numerów, więc najdłużej zaległe — czyli
     * najpilniejsze — nie trafiały nigdzie: ani do pasma, ani na pulpit.
     *
     * Termin to przesunięty albo klienta, tak samo jak w `bandFor()`.
     * Dzisiejsze idą przed zaległymi, bo przy tysiącu zaległych limit
     * wierszy inaczej wypchnąłby wszystko, co trzeba zrobić dziś.
     */
    private function byUrgency(Builder $builder, string $date): Builder
    {
        $deadline = 'COALESCE(orders.shifted_deadline, orders.client_deadline)';
        $final = '(SELECT statuses.is_final FROM statuses WHERE statuses.id = orders.status_id)';

        return $builder
            ->orderByRaw(
                "CASE WHEN {$final} = 1 THEN 4 WHEN {$deadline} IS NULL THEN 3"
                . " WHEN {$deadline} = ? THEN 0 WHEN {$deadline} < ? THEN 1 ELSE 2 END",
                [$date, $date],
            )
            ->orderByRaw("{$deadline} ASC")
            ->orderByDesc('orders.number');
    }

    /**
     * Zlecenia w toku z terminem dziś albo po terminie — regułą pasma.
     */
    private function due(Builder $builder, string $operator, string $date): int
    {
        return $builder
            ->whereHas('status', static fn(Builder $status): Builder => $status->where('is_final', false))
            ->whereRaw('COALESCE(orders.shifted_deadline, orders.client_deadline) ' . $operator . ' ?', [$date])
            ->count();
    }

    private function applySearch(Builder $builder, string $needle): Builder
    {
        $digits = preg_replace('/\D+/', '', $needle) ?? '';

        return $builder->where(static function (Builder $query) use ($needle, $digits): void {
            // Numer zlecenia to jedyna rzecz, jaką klient podaje przez
            // telefon — szukanie po nim musi być pierwsze i po cyfrach.
            if ($digits !== '') {
                $query->where('number', 'like', $digits . '%');
            }

            $query->orWhereHas(
                'contractor',
                static fn(Builder $contractor): Builder => $contractor
                    ->where('name', 'like', '%' . $needle . '%')
                    ->orWhere('short_name', 'like', '%' . $needle . '%'),
            );
        });
    }

    /**
     * Zlecenie w statusie końcowym nie ma terminu do pilnowania —
     * inaczej archiwum zalałoby pasmo zaległych.
     */
    private function bandFor(Order $order, Carbon $day): string
    {
        $deadline = $this->deadline($order);

        if ($deadline === null || ($order->status->is_final ?? false)) {
            return 'later';
        }

        if ($deadline->isSameDay($day)) {
            return 'today';
        }

        return $deadline->lt($day) ? 'overdue' : 'later';
    }

    private function deadline(Order $order): ?Carbon
    {
        return $order->shifted_deadline ?? $order->client_deadline;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function band(string $key, array $rows): array
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += (float) $row['amount'];
        }

        return [
            'key' => $key,
            'count' => count($rows),
            'total' => number_format($total, 2, '.', ''),
            'rows' => $rows,
        ];
    }

    /**
     * @param array<int, list<array<string, mixed>>> $alerts
     * @return array<string, mixed>
     */
    private function row(Order $order, Carbon $day, array $alerts = []): array
    {
        $deadline = $this->deadline($order);
        $step = $this->nextStep->firstAvailable($order);
        $blocked = $step === null ? $this->firstBlocked($order) : null;

        return [
            'id' => (int) $order->getKey(),
            'number' => (int) $order->number,
            'created_at' => $order->getRawOriginal('created_at'),
            'contractor' => $order->contractor?->displayName(),
            'contractor_phone' => $order->contractor?->phone,
            'note' => $order->short_note,
            'status' => $order->status?->name,
            'status_code' => $order->status?->code,
            'deadline' => $deadline?->toDateString(),
            'days_left' => $deadline === null ? null : (int) $day->diffInDays($deadline, false),
            'is_shifted' => $order->shifted_deadline !== null,
            'delivery_method' => $order->delivery_method->value,
            'delivery_place' => $order->pickupLocation->name ?? $order->delivery_address,
            'amount' => $this->value->net($order),
            'owner_initials' => OrderOwnerService::initials($order->owner),
            // Identyfikator, nie tylko inicjaly: pulpit dzieli wiersze
            // na „moje" i reszte, a dwie osoby moga miec te same
            // inicjaly.
            'owner_id' => $order->owner_id === null ? null : (int) $order->owner_id,
            'owner' => $order->owner === null ? null : OrderOwnerService::name($order->owner),
            'is_on_hold' => (bool) $order->is_on_hold,
            'hold_reason' => $order->hold_reason,
            'has_open_claim' => (bool) $order->has_open_claim,
            'next_step' => $step?->toArray(),
            'blocked_step' => $blocked?->toArray(),
            'alerts' => $alerts[(int) $order->getKey()] ?? [],
        ];
    }

    /**
     * Kiedy nic nie jest dostępne, pokazujemy pierwsze zablokowane wraz
     * z powodem. Wiersz bez żadnej informacji o dalszym kroku jest
     * gorszy niż wiersz mówiący, czego brakuje.
     */
    private function firstBlocked(Order $order): ?\App\DTO\Orders\NextStep
    {
        foreach ($this->nextStep->forOrder($order) as $step) {
            // Anulowanie jest dostępne z każdego statusu i zawsze
            // zablokowane brakiem powodu — nie jest podpowiedzią.
            if ($step->target->code === 'ANULOWANE') {
                continue;
            }

            return $step;
        }

        return null;
    }

    /**
     * Zakładki statusów z licznikami. Liczone po stronie bazy — lista
     * pokazuje najwyżej dwieście wierszy, a licznik ma mówić o całości.
     * To samo dotyczy czerwonego licznika alertów obok.
     *
     * **Filtr „moje" obowiązuje też liczniki.** Zakładka mówiąca
     * „Produkcja 40" nad listą czterech własnych zleceń nie jest
     * pomyłką na ekranie, tylko drugą definicją tego samego zbioru —
     * a takie rozjazdy nie zgłaszają się same.
     *
     * @return list<array<string, mixed>>
     */
    private function filters(Carbon $day, ?int $ownerId = null): array
    {
        $alerts = $this->alerts->orderCounts($day, $ownerId);

        /** @var array<int, int> $counts */
        $counts = Order::query()
            ->when(
                $ownerId !== null,
                static fn(Builder $builder): Builder => $builder->where('owner_id', $ownerId),
            )
            ->selectRaw('status_id, count(*) as total')
            ->groupBy('status_id')
            ->pluck('total', 'status_id')
            ->all();

        /** @var Collection<int, Status> $statuses */
        $statuses = Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        $final = Status::query()
            ->where('domain', StatusDomain::ORDER->value)
            ->where('is_final', true)
            ->pluck('id')
            ->map(static fn(mixed $id): int => (int) $id)
            ->all();

        // Zakladka bez statusu pokazuje sprawy w toku, wiec i jej licznik
        // liczy tylko je. „Wszystkie 10 210" nad lista bez archiwum
        // bylby druga definicja tego samego zbioru.
        $open = 0;

        foreach ($counts as $statusId => $count) {
            if (!in_array((int) $statusId, $final, true)) {
                $open += (int) $count;
            }
        }

        $filters = [[
            'code' => null,
            'name' => 'W toku',
            'count' => $open,
            'alerts' => $alerts[''] ?? 0,
            'is_final' => false,
        ]];

        foreach ($statuses as $status) {
            $count = $counts[(int) $status->getKey()] ?? 0;

            // Status bez ani jednego zlecenia nie zasługuje na zakładkę —
            // stary system pokazywał kilkanaście pustych.
            if ($count === 0) {
                continue;
            }

            $filters[] = [
                'code' => $status->code,
                'name' => $status->name,
                'count' => $count,
                'alerts' => $alerts[$status->code] ?? 0,
                // Zamkniete ekran zbiera w jedna grupe — domyslnie lista
                // ich nie pokazuje, a dwanascie zakladek w jednym rzedzie
                // wypychalo reszte paska poza ekran.
                'is_final' => (bool) $status->is_final,
            ];
        }

        return $filters;
    }
}
