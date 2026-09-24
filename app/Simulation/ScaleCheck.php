<?php

declare(strict_types=1);

namespace App\Simulation;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\SearchService;
use Illuminate\Support\Facades\Auth;
use App\Services\DashboardService;
use Database\Seeders\Core\RoleSeeder;
use App\Services\Alerts\AlertBoard;
use App\Models\AlertRule;
use App\Alerts\ConditionCatalog;
use App\Models\AlertOccurrence;
use App\Services\Alerts\AlertEngine;
use App\Services\Orders\OrderCard;
use App\Services\Orders\OrderValue;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderBoardService;
use App\Services\Tempering\TemperingBoard;
use App\Services\Production\ProductionQueue;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pomiar ekranów na dużej bazie i porównanie ich liczb z bazą.
 *
 * **Dwie tabele, dwa pytania.** Czasy mówią, co jest wolne. Rozjazdy
 * mówią, co kłamie — i to one są ważniejsze: wolny ekran widać od razu,
 * a liczba zaniżona przez limit wierszy wygląda dokładnie tak samo jak
 * prawdziwa.
 *
 * Każdy pomiar dostaje świeży obiekt usługi. Silnik alertów i tablica
 * alertów pamiętają wynik na czas żądania; wspólna instancja
 * mierzyłaby drugi raz już tylko odczyt z pamięci.
 */
final class ScaleCheck
{
    public function __construct(
        private readonly Probe $probe = new Probe(),
    ) {
    }

    /**
     * @return array{
     *     timings: list<array{screen: string, ms: float, queries: int, note: string}>,
     *     gaps: list<array{what: string, screen: int|null, truth: int, note: string}>,
     *     counts: array{orders: int, items: int},
     *     rules: list<array{code: string, matched: int, find_ms: float, find_queries: int, subjects_ms: float, subjects_queries: int, open_ms: float, open_queries: int}>
     * }
     */
    public function run(?Carbon $today = null): array
    {
        $day = ($today ?? Carbon::today())->startOfDay();
        $admin = $this->admin();
        $seller = $this->seller();
        $biggest = $this->biggestOrder();

        $timings = [];

        // Silnik najpierw i osobno: pierwszy przebieg na nowej bazie
        // otwiera wystapienia dla calej bazy naraz, a kazdy nastepny
        // tylko je uzgadnia. Zmierzony w srodku listy zleceń zlalby sie
        // z nia w jedna liczbe.
        $cold = $this->probe->measure(static fn(): array => (new AlertEngine())->run($day));
        $timings[] = $this->timing('Alerty — pierwszy przebieg', $cold, sprintf('%d otwartych', count($cold['result'])));

        $warm = $this->probe->measure(static fn(): array => (new AlertEngine())->run($day));
        $timings[] = $this->timing('Alerty — kolejny przebieg', $warm, 'uzgodnienie bez zmian');

        $list = $this->probe->measure(static fn(): array => (new OrderBoardService())->board(today: $day));
        $timings[] = $this->timing('Lista zleceń', $list, sprintf('%d wierszy', $list['result']['summary']['shown']));

        // Tak, jak liste widzi przegladarka: wiersze bez alertow, potem
        // alerty pokazanych wierszy osobnym zapytaniem.
        $rowsOnly = $this->probe->measure(
            static fn(): array => (new OrderBoardService())->board(today: $day, limit: 50, withAlerts: false),
        );
        $timings[] = $this->timing('Lista zleceń — wiersze (50)', $rowsOnly, 'bez alertów, to widać pierwsze');

        $pageIds = [];

        foreach ($rowsOnly['result']['bands'] as $band) {
            foreach ($band['rows'] as $row) {
                $pageIds[] = (int) $row['id'];
            }
        }

        $listAlerts = $this->probe->measure(static fn(): array => (new OrderBoardService())->alerts($pageIds, $day));
        $timings[] = $this->timing('Lista zleceń — alerty strony', $listAlerts, 'dochodzą po wierszach');

        if ($admin !== null) {
            $mine = $this->probe->measure(
                static fn(): array => (new OrderBoardService())->board(today: $day, ownerId: (int) $admin->getKey()),
            );
            $timings[] = $this->timing('Lista zleceń — tylko moje', $mine, sprintf('%d wierszy', $mine['result']['summary']['shown']));
        }

        $dashboard = null;

        if ($admin !== null) {
            // Pulpit jak w przegladarce: najpierw bez pasma alertow, potem
            // pasmo osobno. Rozjazdy porownuja zlozenie obu.
            $first = $this->probe->measure(static fn(): array => (new DashboardService())->board($admin, $day, withAlerts: false));
            $timings[] = $this->timing('Pulpit — administrator', $first, sprintf('%d spraw, bez alertów', $first['result']['summary']['tasks']));

            $band = $this->probe->measure(static fn(): array => (new DashboardService())->alerts($admin, $day));
            $timings[] = $this->timing('Pulpit — pasmo alertów', $band, sprintf('%d reguł', count($band['result'])));

            $dashboard = ['result' => ['alerts' => $band['result']] + $first['result']];
        }

        if ($seller !== null) {
            $sellerBoard = $this->probe->measure(static fn(): array => (new DashboardService())->board($seller, $day, withAlerts: false));
            $timings[] = $this->timing('Pulpit — handlowiec', $sellerBoard, sprintf('%d spraw', $sellerBoard['result']['summary']['tasks']));
        }

        if ($biggest !== null) {
            $card = $this->probe->measure(static fn(): array => (new OrderCard())->card($biggest['id'], $day));
            $timings[] = $this->timing('Karta największego zlecenia', $card, sprintf('#%d, %d formatek', $biggest['number'], $biggest['items']));

            $panes = $this->probe->measure(static fn(): array => (new OrderItemService())->board($biggest['id']));
            $timings[] = $this->timing('Formatki największego zlecenia', $panes, sprintf('#%d', $biggest['number']));
        }

        $queue = $this->probe->measure(static fn(): array => (new ProductionQueue())->board(today: $day));
        $timings[] = $this->timing('Kolejka produkcji', $queue, sprintf('%d etapów', $queue['result']['summary']['total']));

        $furnace = $this->probe->measure(static fn(): array => (new TemperingBoard())->queue());
        $timings[] = $this->timing('Kolejka pieca', $furnace, sprintf('%d pozycji', $furnace['result']['summary']['shown']));

        $furnaceTile = $this->probe->measure(static fn(): int => (new TemperingBoard())->count());
        $timings[] = $this->timing('Kafelek pieca na pulpicie', $furnaceTile, sprintf('%d pozycji', $furnaceTile['result']));

        // Wyszukiwarka pyta o uprawnienie osobno dla kazdej grupy,
        // a konsola nie ma zalogowanego uzytkownika — pierwszy raport
        // pokazal „0,9 ms, 0 zapytan", czyli pomiar niczego wygladajacy
        // jak szybki ekran. Stad szukanie jako administrator i liczba
        // trafien w uwagach: zero ma byc widac.
        if ($admin !== null) {
            Auth::setUser($admin);

            foreach (['2400' => 'po numerze', 'sp' => 'po nazwie'] as $needle => $label) {
                $found = $this->probe->measure(static fn(): array => (new SearchService())->search((string) $needle));
                $timings[] = $this->timing(
                    'Wyszukiwarka — ' . $label,
                    $found,
                    sprintf('„%s": %d trafień', $needle, $this->hits($found['result'])),
                );
            }

            Auth::forgetUser();
        }

        return [
            'timings' => $timings,
            'gaps' => $this->gaps($day, $list['result'], $dashboard['result'] ?? null),
            'counts' => [
                'orders' => Order::query()->count(),
                'items' => OrderItem::query()->count(),
            ],
            'rules' => $this->rules($day),
        ];
    }

    /**
     * Silnik alertów rozłożony na reguły — gdzie idzie czas przebiegu.
     *
     * Mierzone są wyłącznie odczyty: warunek, podpisy pięciu pokazywanych
     * rzeczy i otwarte wystąpienia z odhaczającym. Uzgodnienie (zapisy) zostaje w pomiarze
     * całego przebiegu wyżej, żeby rozbicie niczego w bazie nie zmieniało.
     * Suma kolumn nie musi dać czasu przebiegu: różnica to uzgadnianie
     * i składanie wierszy w PHP.
     *
     * @return list<array{code: string, matched: int, find_ms: float, find_queries: int, subjects_ms: float, subjects_queries: int, open_ms: float, open_queries: int}>
     */
    private function rules(Carbon $day): array
    {
        $catalog = new ConditionCatalog();
        $rows = [];

        /** @var iterable<AlertRule> $rules */
        $rules = AlertRule::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($rules as $rule) {
            /** @var array<string, mixed> $params */
            $params = $rule->condition;
            $type = is_string($params['type'] ?? null) ? $params['type'] : $rule->code;
            $condition = $catalog->find($type);

            if ($condition === null) {
                continue;
            }

            $find = $this->probe->measure(static fn(): array => $condition->find($day, $params));
            $ids = array_map(intval(...), array_keys($find['result']));
            // Podpisy dla pieciu, tak jak prosi o nie pasmo pulpitu —
            // silnik nie liczy ich juz dla wszystkich zapalonych.
            $subjects = $this->probe->measure(static fn(): array => $condition->subjects(array_slice($ids, 0, 5)));
            // Celowo `get()`, nie `count()` w bazie: silnik wczytuje te
            // wiersze (bez modeli, ze zlaczonym odhaczajacym) i to ten
            // koszt mierzymy.
            $open = $this->probe->measure(static fn(): int => count(AlertOccurrence::query()
                ->toBase()
                ->leftJoin('users', 'users.id', '=', 'alert_occurrences.acknowledged_by')
                ->where('alert_occurrences.alert_rule_id', $rule->getKey())
                ->where('alert_occurrences.alertable_type', $condition->alertable())
                ->whereNull('alert_occurrences.resolved_at')
                ->get([
                    'alert_occurrences.id',
                    'alert_occurrences.alertable_id',
                    'alert_occurrences.triggered_at',
                    'alert_occurrences.acknowledged_at',
                    'alert_occurrences.acknowledged_value',
                    'users.first_name',
                    'users.last_name',
                ])
                ->all()));

            $rows[] = [
                'code' => (string) $rule->code,
                'matched' => count($ids),
                'find_ms' => $find['ms'],
                'find_queries' => $find['queries'],
                'subjects_ms' => $subjects['ms'],
                'subjects_queries' => $subjects['queries'],
                'open_ms' => $open['ms'],
                'open_queries' => $open['queries'],
            ];
        }

        return $rows;
    }

    /**
     * Liczby z ekranów zestawione z bazą.
     *
     * „Prawda" liczona jest tą samą regułą, co pasmo na liście: termin
     * przesunięty albo klienta, status niekońcowy. Gdyby tu stała inna
     * definicja, raport porównywałby dwie reguły, a nie ekran z bazą.
     *
     * @param array<string, mixed> $list
     * @param array<string, mixed>|null $dashboard
     * @return list<array{what: string, screen: int|null, truth: int, note: string}>
     */
    private function gaps(Carbon $day, array $list, ?array $dashboard): array
    {
        $open = static fn(): Builder => Order::query()->whereHas(
            'status',
            static fn(Builder $status): Builder => $status->where('is_final', false),
        );

        $overdue = $open()
            ->whereRaw('COALESCE(shifted_deadline, client_deadline) < ?', [$day->toDateString()])
            ->count();

        $dueToday = $open()
            ->whereRaw('COALESCE(shifted_deadline, client_deadline) = ?', [$day->toDateString()])
            ->count();

        $inProgress = $open()->count();

        /** @var array<string, mixed> $summary */
        $summary = $list['summary'];
        /** @var list<array<string, mixed>> $filters */
        $filters = $list['filters'];

        $rows = [
            [
                'what' => 'Lista zleceń — zakładka „W toku"',
                'screen' => (int) ($filters[0]['count'] ?? 0),
                'truth' => $inProgress,
                'note' => 'zlecenia w statusach niezamkniętych',
            ],
            [
                'what' => 'Lista zleceń — „z ilu" pod stroną',
                'screen' => (int) ($summary['total'] ?? 0),
                'truth' => $inProgress,
                'note' => 'domyślny widok: sprawy w toku',
            ],
            [
                'what' => 'Lista zleceń — pasek „Zaległe"',
                'screen' => (int) $summary['overdue'],
                'truth' => $overdue,
                'note' => 'reguła pasma',
            ],
            [
                'what' => 'Lista zleceń — pasek „Dziś"',
                'screen' => (int) $summary['today'],
                'truth' => $dueToday,
                'note' => 'reguła pasma',
            ],
        ];

        if ($dashboard !== null) {
            /** @var array<string, int|null> $counters */
            $counters = $dashboard['counters'];

            $rows[] = [
                'what' => 'Pulpit — kafelek „Po terminie"',
                'screen' => $counters['overdue'],
                'truth' => $overdue,
                'note' => 'reguła pasma',
            ];
            $rows[] = [
                'what' => 'Pulpit — kafelek „Na dziś"',
                'screen' => $counters['today'],
                'truth' => $dueToday,
                'note' => 'reguła pasma',
            ];

            /** @var list<array<string, mixed>> $alerts */
            $alerts = $dashboard['alerts'];

            foreach ($alerts as $alert) {
                if ($alert['code'] === 'order_overdue') {
                    $rows[] = [
                        'what' => 'Pulpit — alert „po terminie"',
                        'screen' => (int) $alert['count'],
                        'truth' => $overdue,
                        'note' => 'silnik liczy całą bazę',
                    ];
                }
            }
        }

        $rows[] = $this->storedValues();

        return $rows;
    }

    /**
     * Wartość zapamiętana na zleceniu a ta sama wartość policzona od nowa.
     *
     * Zapamiętana kwota, której nikt nie odświeżył, wygląda dokładnie
     * tak samo jak prawdziwa — stąd porównanie na próbce, przy każdym
     * pomiarze. „Ekran" to liczba zgodnych, „baza" to wielkość próbki.
     *
     * @return array{what: string, screen: int|null, truth: int, note: string}
     */
    private function storedValues(): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Order> $sample */
        $sample = Order::query()
            ->with(['lists.items.processes', 'discounts', 'invoiceType'])
            ->where('value_stale', false)
            ->inRandomOrder()
            ->limit(200)
            ->get();

        $value = new OrderValue();
        $same = 0;

        foreach ($sample as $order) {
            $totals = $value->totals($order);

            if ((string) $order->value_net === $totals->net && $order->value_gross === $totals->gross) {
                $same++;
            }
        }

        return [
            'what' => sprintf('Wartość zapisana a OrderValue (próbka %d)', $sample->count()),
            'screen' => $same,
            'truth' => $sample->count(),
            'note' => sprintf('%d zleceń czeka na przeliczenie', Order::query()->where('value_stale', true)->count()),
        ];
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function hits(array $groups): int
    {
        $total = 0;

        foreach ($groups as $group) {
            $total += is_array($group['hits'] ?? null) ? count($group['hits']) : 0;
        }

        return $total;
    }

    /**
     * @param array{ms: float, queries: int, result: mixed} $measure
     * @return array{screen: string, ms: float, queries: int, note: string}
     */
    private function timing(string $screen, array $measure, string $note): array
    {
        return ['screen' => $screen, 'ms' => $measure['ms'], 'queries' => $measure['queries'], 'note' => $note];
    }

    private function admin(): ?User
    {
        /** @var User|null */
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', static fn(Builder $role): Builder => $role->where('is_superuser', true))
            ->orderBy('id')
            ->first();
    }

    private function seller(): ?User
    {
        /** @var User|null */
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', static fn(Builder $role): Builder => $role->where('name', RoleSeeder::SALES))
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{id: int, number: int, items: int}|null
     */
    private function biggestOrder(): ?array
    {
        $row = OrderItem::query()
            ->join('order_lists', 'order_lists.id', '=', 'order_items.order_list_id')
            ->join('orders', 'orders.id', '=', 'order_lists.order_id')
            ->selectRaw('orders.id as id, orders.number as number, COUNT(*) as items')
            ->groupBy('orders.id', 'orders.number')
            ->orderByDesc('items')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->getAttribute('id'),
            'number' => (int) $row->getAttribute('number'),
            'items' => (int) $row->getAttribute('items'),
        ];
    }
}
