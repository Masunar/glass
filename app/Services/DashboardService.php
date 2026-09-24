<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Enum\Permission;
use Salvon\Enum\SubPermission;
use App\Support\AccessRegistry;
use App\Services\Offers\OfferBoard;
use App\Services\Alerts\AlertBoard;
use App\Services\Warehouse\StockBoard;
use App\Services\Orders\OrderBoardService;
use App\Services\Tempering\TemperingBoard;
use App\Services\Production\ProductionQueue;

/**
 * Pulpit — jedna lista spraw, pas liczb i to, co blokuje.
 *
 * **Pulpit niczego nie liczy sam.** Każda liczba pochodzi z tej samej
 * usługi, co ekran, do którego prowadzi: pasma terminów z
 * `OrderBoardService`, kolejka z `ProductionQueue`, braki ze
 * `StockBoard`. Własna definicja „zaległego" rozjechałaby się z listami
 * przy pierwszej zmianie reguły — i byłby to rozjazd bez objawów.
 *
 * **Sekcje przycina serwer, nie front.** Adres `/` jest otwarty dla
 * każdego zalogowanego, więc filtrowanie w przeglądarce oddawałoby
 * kwoty ofert komuś, kto ich nie widzi na ekranie.
 *
 * Pierwsza wersja dzieliła ekran na „moje" i „firmowe" i w jednoosobowym
 * biurze pokazywała **tę samą listę dwa razy**. Jest jedna lista spraw:
 * zlecenie z dostępnym ruchem albo po terminie. Reszta to liczby
 * i blokady.
 */
final readonly class DashboardService
{
    /** Ile wierszy w krótkich listach obok głównej. */
    private const ROWS = 5;

    private AlertBoard $alerts;

    private OrderBoardService $orders;

    public function __construct(
        ?AlertBoard $alerts = null,
        ?OrderBoardService $orders = null,
        private OfferBoard $offers = new OfferBoard(),
        private ProductionQueue $production = new ProductionQueue(),
        private TemperingBoard $tempering = new TemperingBoard(),
        private StockBoard $stock = new StockBoard(),
    ) {
        // Jeden przebieg silnika na zadanie. Lista zlecen i pasmo alertow
        // pytaja o to samo, a przebieg uzgadnia wystapienia w bazie —
        // dwa niezalezne silniki robilyby te sama prace dwa razy.
        $this->alerts = $alerts ?? new AlertBoard();
        $this->orders = $orders ?? new OrderBoardService(alerts: $this->alerts);
    }

    /**
     * @return array<string, mixed>
     */
    public function board(User $user, ?Carbon $today = null): array
    {
        $day = ($today ?? Carbon::today())->startOfDay();

        $seesOrders = $this->may($user, Permission::ORDERS, 'zlec');
        $rows = $seesOrders ? $this->orderRows($day) : [];

        $tasks = $this->tasks($rows, (int) $user->getKey());
        $counters = $this->counters($user, $rows, $seesOrders);

        return [
            'as_of' => $day->toDateString(),
            'user' => [
                'name' => $user->first_name,
                'location' => $user->location?->name,
            ],
            'summary' => [
                'tasks' => count($tasks),
                'overdue' => $this->countBand($tasks, 'overdue'),
                'today' => $this->countBand($tasks, 'today'),
                'later' => $this->countBand($tasks, 'later'),
            ],
            // Pierwsza sprawa na liscie jest jednoczesnie propozycja
            // startu: „zacznij od #24004" zamiast „masz siedem spraw".
            'top' => $tasks[0] ?? null,
            'counters' => $counters,
            'alerts' => $this->alerts($user, $day),
            'tasks' => $tasks,
            'blocked' => $seesOrders ? $this->blocked($rows) : [],
            'shortages' => $this->shortages($user),
        ];
    }

    /**
     * Pasmo alertów: reguła, ile razy zapalona, dokąd prowadzi.
     *
     * Alert nie ma własnego uprawnienia do odczytu — widzi go ten, kto
     * widzi rzecz, której alert dotyczy. `alerts` chroni ekran reguł,
     * czyli konfigurację, a nie dane.
     *
     * **Adresat jest wyprowadzany, nie zapisany.** Reguły z moimi
     * sprawami idą pierwsze, a w obrębie reguły pierwsze idą moje
     * podpisy — cudze zostają, z inicjałami prowadzącego. Nic nie
     * znika: zlecenie po terminie jest po terminie niezależnie od tego,
     * czyje jest, a ktoś musi je zobaczyć, gdy prowadzący ma urlop.
     *
     * **Przycina moduł reguły, nie zlecenia.** Od chwili, gdy alerty
     * objęły magazyn i piec, wspólna bramka „czy widzi zlecenia" byłaby
     * albo za wąska (magazynier nie zobaczyłby braków), albo za szeroka
     * (handlowiec zobaczyłby piec). Każdy wiersz pyta o swój moduł.
     *
     * @return list<array<string, mixed>>
     */
    private function alerts(User $user, Carbon $day): array
    {
        $rows = [];

        $me = (int) $user->getKey();

        foreach ($this->alerts->summary($day, $me) as $row) {
            $module = is_string($row['module']) ? $row['module'] : '';

            $resource = is_string($row['resource']) ? $row['resource'] : '';

            // Dwa poziomy (U-04), tak jak przy licznikach: dostep do
            // modulu i uprawnienie do zasobu. Samo `mag.access` bez
            // `warehouse.list` oddaloby liczbe brakow komus, kto nie
            // widzi ani jednej pozycji magazynu.
            if ($module === '' || !$user->can($module . '.' . AccessRegistry::ACCESS)) {
                continue;
            }

            if ($resource === '' || !$user->can($resource . '.' . SubPermission::LIST->value)) {
                continue;
            }

            $row['subjects'] = $this->alerts->subjectsFor(
                (string) $row['code'],
                self::ROWS,
                $day,
                $me,
            );
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Czy użytkownik widzi zasób **i** moduł, w którym on leży.
     *
     * Dwa poziomy (U-04), więc pulpit pyta o oba. Pytanie tylko
     * o `orders.list` przepuściłoby kogoś, komu listwa i tak chowa cały
     * moduł Zlecenia.
     */
    private function may(User $user, Permission $permission, string $module): bool
    {
        return $user->can($module . '.' . AccessRegistry::ACCESS)
            && $user->can($permission->value . '.' . SubPermission::LIST->value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderRows(Carbon $day): array
    {
        $board = $this->orders->board(null, null, $day);
        $rows = [];

        /** @var list<array<string, mixed>> $bands */
        $bands = $board['bands'];

        foreach ($bands as $band) {
            /** @var list<array<string, mixed>> $bandRows */
            $bandRows = $band['rows'];

            foreach ($bandRows as $row) {
                $row['band'] = $band['key'];
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Sprawa = zlecenie, z którym da się coś teraz zrobić, albo takie,
     * które już się spóźnia.
     *
     * Zablokowane **nie są** sprawami: `firstBlocked()` zwraca coś przy
     * prawie każdym zleceniu, więc lista zamieniłaby się w kopię listy
     * zleceń. Blokady mają własne miejsce i są liczone po powodzie.
     *
     * Kolejność: najpierw po terminie (najdłużej stojące na górze),
     * potem dzisiejsze, na końcu reszta.
     *
     * **Wewnątrz pasma pierwsze idą sprawy prowadzone przeze mnie.**
     * Nie jako osobna sekcja — ta wersja już raz istniała i w
     * jednoosobowym biurze pokazywała tę samą listę dwa razy. Cudze
     * zlecenie po terminie dalej jest po terminie i zostaje na liście:
     * pulpit mówi, co pilne, a nie co czyje.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function tasks(array $rows, int $userId): array
    {
        $tasks = [];

        foreach ($rows as $row) {
            // Pasmo przychodzi z listy i **nie jest liczone drugi raz**.
            // Liczone tutaj z samego `days_left` gubilo regule, ktora
            // lista stosuje swiadomie: zlecenie w statusie koncowym nie
            // ma terminu do pilnowania. Zamkniete zlecenie sprzed
            // miesiaca wracalo przez to na pulpit jako sprawa na dzis,
            // a kafelek i zakladka pokazywaly dwie rozne liczby.
            $late = $row['band'] === 'overdue';

            if ($row['next_step'] === null && !$late) {
                continue;
            }

            $row['deadline_label'] = $this->deadlineLabel($row['days_left']);
            // „Moje" znaczy **prowadzacy = ja**, tak samo jak filtr na
            // liscie zlecen. Zakladajacy byl tu bez znaczenia juz
            // wczesniej; teraz ma wlasne pole i ta sama definicja stoi
            // w obu miejscach.
            $row['is_mine'] = $row['owner_id'] !== null && (int) $row['owner_id'] === $userId;
            $tasks[] = $row;
        }

        // Najpierw pasmo, potem **moje sprawy**, potem to, co da sie
        // ruszyc, dopiero na koncu dlugosc spoznienia. Zlecenie stojace trzydziesci dni
        // bez zadnego dostepnego przejscia nie jest dobrym poczatkiem
        // dnia — jest na nie za pozno, zeby zaczynac od niego.
        usort($tasks, static function (array $a, array $b): int {
            $order = ['overdue' => 0, 'today' => 1, 'later' => 2];

            return [
                $order[$a['band']],
                $a['is_mine'] === true ? 0 : 1,
                $a['next_step'] === null ? 1 : 0,
                $a['days_left'] ?? PHP_INT_MAX,
            ] <=> [
                $order[$b['band']],
                $b['is_mine'] === true ? 0 : 1,
                $b['next_step'] === null ? 1 : 0,
                $b['days_left'] ?? PHP_INT_MAX,
            ];
        });

        return $tasks;
    }

    /**
     * Termin słowem, nie surową datą — „dziś", „jutro", „5 dni po".
     *
     * Reguła stoi tutaj, a nie na ekranie: gdyby liczył ją front,
     * pulpit i lista zleceń mogłyby nazwać ten sam dzień inaczej.
     */
    private function deadlineLabel(mixed $days): ?string
    {
        if ($days === null) {
            return null;
        }

        $left = (int) $days;

        return match (true) {
            $left < 0 => abs($left) . ' dni po',
            $left === 0 => 'dziś',
            $left === 1 => 'jutro',
            default => 'za ' . $left . ' dni',
        };
    }

    /**
     * @param list<array<string, mixed>> $tasks
     */
    private function countBand(array $tasks, string $band): int
    {
        return count(array_filter(
            $tasks,
            static fn(array $row): bool => $row['band'] === $band,
        ));
    }

    /**
     * Pas liczb. `null` znaczy „nie masz do tego dostępu" i zostaje
     * kreską na ekranie — inaczej zero i brak uprawnienia wyglądałyby
     * tak samo.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, int|null>
     */
    private function counters(User $user, array $rows, bool $seesOrders): array
    {
        $overdue = null;
        $today = null;

        if ($seesOrders) {
            $overdue = count(array_filter(
                $rows,
                static fn(array $row): bool => $row['band'] === 'overdue',
            ));
            $today = count(array_filter(
                $rows,
                static fn(array $row): bool => $row['band'] === 'today',
            ));
        }

        $production = null;

        if ($this->may($user, Permission::PRODUCTION, 'prod')) {
            /** @var array<string, mixed> $summary */
            $summary = $this->production->board()['summary'];
            $production = (int) $summary['shown'];
        }

        $furnace = null;

        if ($this->may($user, Permission::TEMPERING, 'prod')) {
            /** @var array<string, mixed> $summary */
            $summary = $this->tempering->queue()['summary'];
            $furnace = (int) $summary['shown'];
        }

        $offers = null;

        if ($this->may($user, Permission::OFFERS, 'zlec')) {
            /** @var list<array<string, mixed>> $list */
            $list = $this->offers->board()['offers'];
            $offers = count(array_filter(
                $list,
                static fn(array $row): bool => ($row['is_open'] ?? false) === true,
            ));
        }

        $shortages = null;

        if ($this->may($user, Permission::WAREHOUSE, 'mag')) {
            /** @var list<array<string, mixed>> $list */
            $list = $this->stock->levels(null, true)['rows'];
            $shortages = count($list);
        }

        return [
            'overdue' => $overdue,
            'today' => $today,
            'shortages' => $shortages,
            'production' => $production,
            'furnace' => $furnace,
            'offers' => $offers,
        ];
    }

    /**
     * Co blokuje zlecenia — **po powodzie, nie po zleceniu**.
     *
     * „Trzy zlecenia bez potwierdzenia rysunków" mówi, co zrobić; trzy
     * wiersze z tym samym zdaniem każą to dopiero policzyć.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array{reason: string, count: int}>
     */
    private function blocked(array $rows): array
    {
        /** @var array<string, int> $grouped */
        $grouped = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed>|null $step */
            $step = $row['blocked_step'];

            if ($step === null) {
                continue;
            }

            $reason = trim((string) ($step['blocked_by'] ?? ''));

            if ($reason === '') {
                continue;
            }

            $grouped[$reason] = ($grouped[$reason] ?? 0) + 1;
        }

        arsort($grouped);

        return array_map(
            static fn(string $reason, int $count): array => [
                'reason' => $reason,
                'count' => $count,
            ],
            array_keys($grouped),
            array_values($grouped),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function shortages(User $user): ?array
    {
        if (!$this->may($user, Permission::WAREHOUSE, 'mag')) {
            return null;
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->stock->levels(null, true)['rows'];

        return [
            'total' => count($rows),
            'rows' => array_map(
                static fn(array $row): array => [
                    'product_id' => $row['product_id'],
                    'name' => $row['name'],
                    'available' => $row['available'],
                    // Prog, nie sugestia zakupu: pasek ma pokazac, jak
                    // daleko do stanu docelowego.
                    'max' => $row['max'],
                ],
                array_slice($rows, 0, self::ROWS),
            ),
        ];
    }
}
