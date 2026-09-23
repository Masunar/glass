<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Enum\Permission;
use Salvon\Enum\SubPermission;
use App\Support\AccessRegistry;
use App\Services\Offers\OfferBoard;
use App\Services\Warehouse\StockBoard;
use App\Services\Orders\OrderBoardService;
use App\Services\Tempering\TemperingBoard;
use App\Services\Production\ProductionQueue;

/**
 * Pulpit — „co czeka na mnie", a pod spodem „co się dzieje".
 *
 * Do #31 pod `/` stało demo Salvona. Pierwszy ekran po zalogowaniu był
 * jedynym miejscem w aplikacji, które nie należało do tej aplikacji.
 *
 * **Pulpit niczego nie liczy sam.** Każda liczba pochodzi z tej samej
 * usługi, co ekran, do którego prowadzi: pasma terminów z
 * `OrderBoardService`, kolejka z `ProductionQueue`, braki ze
 * `StockBoard`. Gdyby pulpit miał własne definicje „zaległego" czy
 * „braku", rozjechałby się z listami przy pierwszej zmianie reguły —
 * i byłby to rozjazd bez objawów, bo obie strony wyglądałyby poprawnie.
 *
 * **Sekcje są przycinane uprawnieniami po stronie serwera.** Sam adres
 * `/` jest otwarty dla każdego zalogowanego, więc gdyby filtrował
 * wyłącznie front, magazynier dostałby kwoty ofert w odpowiedzi API,
 * nawet nie widząc ich na ekranie.
 */
final readonly class DashboardService
{
    /** Ile wierszy pokazujemy w jednej sekcji, zanim odeślemy do listy. */
    private const ROWS = 6;

    public function __construct(
        private OrderBoardService $orders = new OrderBoardService(),
        private OfferBoard $offers = new OfferBoard(),
        private ProductionQueue $production = new ProductionQueue(),
        private TemperingBoard $tempering = new TemperingBoard(),
        private StockBoard $stock = new StockBoard(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function board(User $user, ?Carbon $today = null): array
    {
        $day = ($today ?? Carbon::today())->startOfDay();

        $seesOrders = $this->may($user, Permission::ORDERS, 'zlec');
        $seesOffers = $this->may($user, Permission::OFFERS, 'zlec');

        $orderRows = $seesOrders ? $this->orderRows($day) : [];
        $offerRows = $seesOffers ? $this->offerRows() : [];

        return [
            'as_of' => $day->toDateString(),
            // Sekcja osobista znika w calosci, gdy nic w systemie nie
            // jest adresowane do osoby — patrz `mine()`.
            'mine' => $this->mine($user, $orderRows, $offerRows, $seesOrders, $seesOffers),
            'orders' => $seesOrders ? $this->orders($orderRows) : null,
            'production' => $this->production($user),
            'warehouse' => $this->warehouse($user),
            'offers' => $seesOffers ? $this->offers($offerRows) : null,
        ];
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
     * Zlecenia z listy — wszystkie pasma razem, bo pulpit czyta po
     * pilności kroku, nie po terminie.
     *
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
     * @return list<array<string, mixed>>
     */
    private function offerRows(): array
    {
        $board = $this->offers->board();

        /** @var list<array<string, mixed>> $rows */
        $rows = $board['offers'];

        return $rows;
    }

    /**
     * To, co czeka na zalogowanego.
     *
     * „Moje" znaczy dziś dokładnie dwie rzeczy, bo tylko tyle system
     * przypisuje do osoby: zlecenie, które ktoś założył (`created_by`),
     * i ofertę, którą wystawił. **Zadania produkcji są przypisane do
     * stanowisk, nie do ludzi**, więc dla hali ta sekcja nie ma treści —
     * i wtedy nie pokazujemy jej wcale zamiast wypisywać „nic nie
     * czeka", co byłoby nieprawdą wobec pełnej kolejki.
     *
     * Trafia tu zlecenie z **dostępnym ruchem** albo **po terminie**.
     * Samo „zablokowane" nie wystarcza: `firstBlocked()` zwraca coś
     * przy prawie każdym zleceniu, więc sekcja zamieniłaby się w drugą
     * listę wszystkich moich zleceń. Zlecenie stojące w produkcji nie
     * czeka na handlowca — czeka na halę.
     *
     * @param list<array<string, mixed>> $orderRows
     * @param list<array<string, mixed>> $offerRows
     * @return array<string, mixed>|null
     */
    private function mine(
        User $user,
        array $orderRows,
        array $offerRows,
        bool $seesOrders,
        bool $seesOffers,
    ): ?array {
        if (!$seesOrders && !$seesOffers) {
            return null;
        }

        $id = (int) $user->getKey();

        $orders = array_values(array_filter(
            $orderRows,
            static fn(array $row): bool => $row['owner_id'] === $id
                && ($row['next_step'] !== null
                    || ($row['days_left'] !== null && $row['days_left'] < 0)),
        ));

        $offers = array_values(array_filter(
            $offerRows,
            static fn(array $row): bool => ($row['issued_by_id'] ?? null) === $id
                && ($row['is_open'] ?? false) === true,
        ));

        return [
            'orders' => array_slice($orders, 0, self::ROWS),
            'orders_total' => count($orders),
            'offers' => array_slice($offers, 0, self::ROWS),
            'offers_total' => count($offers),
        ];
    }

    /**
     * Zlecenia: terminy, dostępne ruchy i to, co stoi zablokowane.
     *
     * Zablokowane grupujemy **po powodzie**, nie po zleceniu. „Pięć
     * zleceń czeka na rysunki" mówi, co zrobić; pięć osobnych wierszy
     * z tym samym zdaniem każe to dopiero policzyć.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function orders(array $rows): array
    {
        $ready = array_values(array_filter(
            $rows,
            static fn(array $row): bool => $row['next_step'] !== null,
        ));

        /** @var array<string, int> $blocked */
        $blocked = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed>|null $step */
            $step = $row['blocked_step'];

            if ($step === null) {
                continue;
            }

            $reason = (string) ($step['blocked_by'] ?? '');

            if ($reason === '') {
                continue;
            }

            $blocked[$reason] = ($blocked[$reason] ?? 0) + 1;
        }

        arsort($blocked);

        $counts = ['today' => 0, 'overdue' => 0];

        foreach ($rows as $row) {
            if ($row['band'] === 'today' || $row['band'] === 'overdue') {
                $counts[(string) $row['band']] += 1;
            }
        }

        return [
            'today' => $counts['today'],
            'overdue' => $counts['overdue'],
            'ready' => array_slice($ready, 0, self::ROWS),
            'ready_total' => count($ready),
            'blocked' => array_map(
                static fn(string $reason, int $count): array => [
                    'reason' => $reason,
                    'count' => $count,
                ],
                array_keys($blocked),
                array_values($blocked),
            ),
        ];
    }

    /**
     * Produkcja i hartownia w jednej sekcji — jedna hala.
     *
     * @return array<string, mixed>|null
     */
    private function production(User $user): ?array
    {
        $seesProduction = $this->may($user, Permission::PRODUCTION, 'prod');
        $seesTempering = $this->may($user, Permission::TEMPERING, 'prod');

        if (!$seesProduction && !$seesTempering) {
            return null;
        }

        $queue = null;

        if ($seesProduction) {
            $board = $this->production->board();
            /** @var array<string, mixed> $summary */
            $summary = $board['summary'];
            /** @var list<array<string, mixed>> $rows */
            $rows = $board['rows'];

            $queue = [
                'waiting' => (int) $summary['shown'],
                'problems' => (int) $summary['problems'],
                'overdue' => (int) $summary['overdue'],
                'urgent' => count(array_filter(
                    $rows,
                    static fn(array $row): bool => ($row['is_urgent'] ?? false) === true,
                )),
            ];
        }

        $furnace = null;

        if ($seesTempering) {
            $board = $this->tempering->queue();
            /** @var array<string, mixed> $summary */
            $summary = $board['summary'];

            $furnace = [
                'waiting' => (int) $summary['shown'],
                'kg' => (float) $summary['kg'],
            ];
        }

        return ['queue' => $queue, 'furnace' => $furnace];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function warehouse(User $user): ?array
    {
        if (!$this->may($user, Permission::WAREHOUSE, 'mag')) {
            return null;
        }

        $board = $this->stock->levels(null, true);

        /** @var list<array<string, mixed>> $rows */
        $rows = $board['rows'];

        return [
            'shortages' => count($rows),
            'rows' => array_slice($rows, 0, self::ROWS),
        ];
    }

    /**
     * Oferty czekające u klientów.
     *
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    private function offers(array $rows): array
    {
        $open = array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['is_open'] ?? false) === true,
        ));

        return [
            'open' => count($open),
            'rows' => array_slice($open, 0, self::ROWS),
        ];
    }
}
