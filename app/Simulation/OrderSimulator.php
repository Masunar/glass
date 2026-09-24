<?php

declare(strict_types=1);

namespace App\Simulation;

use Closure;
use Carbon\Carbon;
use App\Enum\Section;
use App\Models\User;
use App\Models\Order;
use App\Models\Status;
use App\Models\Process;
use App\Models\Product;
use App\Models\Location;
use App\Models\Contractor;
use App\Enum\StatusDomain;
use App\Enum\DeliveryMethod;
use App\Services\Orders\OrderService;
use App\Services\Orders\OrderItemService;
use App\Services\Orders\OrderListService;
use App\Services\Orders\OrderTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Zakładanie dużej bazy zleceń **tą samą drogą, co ekran**.
 *
 * Zlecenie powstaje przez `OrderService::create()` jako kolejny
 * użytkownik z bazy, dodatkowe listy przez `OrderListService`, a każda
 * formatka przez `OrderItemService::savePane()` — z walidacją, wyceną
 * z cennika, procesami i wpisem w dzienniku. Wstawianie wierszy obok
 * usług mierzyłoby dane wpisane obok aplikacji, a nie aplikację.
 *
 * **Czego tu nie wymyślamy.** Kontrahenci i konta pochodzą z bazy,
 * materiały z cennika, ceny z wyceny. Produkt bez ceny zostaje bez
 * ceny, tak jak na ekranie. Założeniami symulacji są wyłącznie liczby
 * losowane: wymiary, liczba list i formatek, terminy i **rozkład
 * statusów** — raport mówi o tym wprost, żeby nikt nie wziął go za
 * odczyt z produkcji.
 *
 * Status ustawiany jest wprost, a jego skutki idą przez
 * `OrderTransition::enter()` — tę samą metodę, którą wołają przejście
 * i zasiew demo. Odtwarzany jest tylko bieżący status, bez drogi do
 * niego, więc zlecenie „Gotowe" nie ma zadań, których nikt nie odhaczył.
 */
final class OrderSimulator
{
    /**
     * Rozkład statusów — **założenie symulacji**, nie odczyt z produkcji.
     * Większość bazy to rzeczy zamknięte, bo tak wygląda każda baza
     * po kilku latach pracy.
     */
    public const STATUS_WEIGHTS = [
        'DO_WYCENY' => 12,
        'ZLECENIE' => 12,
        'PRODUKCJA' => 8,
        'GOTOWE' => 5,
        'DOSTAWA' => 2,
        'ODBIOR' => 2,
        'MONTAZ' => 2,
        'NIEROZLICZONE' => 4,
        'ROZLICZONE' => 8,
        'ARCHIWUM' => 38,
        'OFERTA_ODRZUCONA' => 4,
        'ANULOWANE' => 3,
    ];

    /** Bez terminu — zlecenie, na które nikt jeszcze nie umówił daty. */
    private const NO_DEADLINE_PERCENT = 10;

    /** Hartowana szyba dostaje proces H i trafia do kolejki pieca. */
    private const TEMPERED_PERCENT = 30;

    public function __construct(
        private readonly OrderService $orders = new OrderService(),
        private readonly OrderListService $lists = new OrderListService(),
        private readonly OrderItemService $items = new OrderItemService(),
        private readonly OrderTransition $transition = new OrderTransition(),
    ) {
    }

    /**
     * @param Closure(int): void|null $tick wołane po każdym zleceniu
     * @return array{orders: int, lists: int, panes: int, rejected: array<string, int>, seconds: float}
     */
    public function run(int $count, int $seed, ?Closure $tick = null): array
    {
        mt_srand($seed);

        $context = $this->context();
        $today = Carbon::today();
        $started = hrtime(true);

        $stats = ['orders' => 0, 'lists' => 0, 'panes' => 0, 'rejected' => []];

        for ($index = 0; $index < $count; $index++) {
            // Kazde zlecenie w jednej transakcji: przy stu tysiacach
            // formatek zapis wiersz po wierszu zamienialby minuty
            // w pol godziny, a wynik bylby ten sam.
            DB::transaction(function () use ($index, $context, $today, &$stats): void {
                $this->one($index, $context, $today, $stats);
            });

            if ($tick !== null) {
                $tick($index + 1);
            }

            if ($index % 250 === 0) {
                gc_collect_cycles();
            }
        }

        Auth::forgetUser();

        $stats['seconds'] = round((hrtime(true) - $started) / 1_000_000_000, 1);

        return $stats;
    }

    /**
     * @param array{users: list<User>, contractors: list<int>, products: list<int>, pickups: list<int>, processes: array<string, int>, statuses: array<string, int>} $context
     * @param array{orders: int, lists: int, panes: int, rejected: array<string, int>} $stats
     */
    private function one(int $index, array $context, Carbon $today, array &$stats): void
    {
        // Zakladajacy — a wiec i prowadzacy — rozklada sie po kontach
        // z bazy, tak jak w pracy biura.
        Auth::setUser($context['users'][$index % count($context['users'])]);

        $deadline = mt_rand(1, 100) <= self::NO_DEADLINE_PERCENT
            ? null
            : $today->copy()->addDays(mt_rand(-30, 60))->toDateString();

        $created = $this->orders->create([
            'contractor_id' => $this->pick($context['contractors']),
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'pickup_location_id' => $this->pick($context['pickups']),
            'client_deadline' => $deadline,
        ]);

        if ($created['errors'] !== [] || $created['id'] === null) {
            // Zlecenie odrzucone przez walidacje to blad symulacji, nie
            // dane — lepiej przerwac niz mierzyc polowe bazy.
            throw new RuntimeException('Zakładanie zlecenia odrzucone: ' . json_encode($created['errors']));
        }

        $orderId = $created['id'];
        $stats['orders']++;

        /** @var Order $order */
        $order = Order::query()->with('lists')->findOrFail($orderId);

        /** @var list<int> $listIds */
        $listIds = [(int) $order->lists->first()?->getKey()];

        for ($extra = mt_rand(1, 3) - 1; $extra > 0; $extra--) {
            $list = $this->lists->save($orderId, ['role' => 'component', 'is_included' => true]);

            if ($list['id'] !== null) {
                $listIds[] = (int) $list['id'];
            }
        }

        $stats['lists'] += count($listIds);

        foreach ($listIds as $listId) {
            for ($pane = mt_rand(1, 10); $pane > 0; $pane--) {
                $tempered = mt_rand(1, 100) <= self::TEMPERED_PERCENT;

                $processes = [
                    ['process_id' => $context['processes']['C']],
                    ['process_id' => $context['processes']['S']],
                ];

                if ($tempered && isset($context['processes']['H'])) {
                    $processes[] = ['process_id' => $context['processes']['H']];
                }

                $saved = $this->items->savePane($orderId, [
                    'order_list_id' => $listId,
                    'product_id' => $this->pick($context['products']),
                    'width_mm' => mt_rand(150, 2800),
                    'height_mm' => mt_rand(150, 2000),
                    'quantity' => mt_rand(1, 4),
                    'is_tempered' => $tempered,
                    'processes' => $processes,
                ]);

                if ($saved['errors'] !== []) {
                    // Odrzucona formatka jest wynikiem, nie szumem: znaczy,
                    // ze ekran odrzucilby to samo, co wpisal czlowiek.
                    $key = (string) array_key_first($saved['errors']);
                    $stats['rejected'][$key] = ($stats['rejected'][$key] ?? 0) + 1;

                    continue;
                }

                $stats['panes']++;
            }
        }

        $code = $this->weighted(self::STATUS_WEIGHTS);

        $order->status_id = $context['statuses'][$code];
        $order->save();

        $this->transition->enter($order->fresh() ?? $order, $code);
    }

    /**
     * Wszystko, co symulacja bierze z bazy — raz, przed pętlą.
     *
     * @return array{users: list<User>, contractors: list<int>, products: list<int>, pickups: list<int>, processes: array<string, int>, statuses: array<string, int>}
     */
    private function context(): array
    {
        /** @var list<User> $users */
        $users = User::query()->where('is_active', true)->orderBy('id')->get()->all();

        $contractors = $this->ids(Contractor::query()->pluck('id')->all());

        $products = $this->ids(Product::query()
            ->where('section', Section::GLASS->value)
            ->where('is_active', true)
            ->pluck('id')
            ->all());

        $pickups = $this->ids(Location::query()
            ->where('is_pickup_point', true)
            ->pluck('id')
            ->all());

        // Pusta kartoteka to nie jest powod, zeby cos wymyslic —
        // symulacja mowi, czego brakuje, i konczy.
        $missing = array_keys(array_filter([
            'aktywne konta' => $users === [],
            'kontrahenci' => $contractors === [],
            'szkło w cenniku' => $products === [],
            'punkty odbioru' => $pickups === [],
        ]));

        if ($missing !== []) {
            throw new RuntimeException(
                'Brak danych do symulacji: ' . implode(', ', $missing) . '. Najpierw `make migrate-fresh-seed`.',
            );
        }

        $processes = [];

        foreach (['C', 'S', 'H'] as $code) {
            $process = Process::findByCode($code);

            if ($process !== null) {
                $processes[$code] = (int) $process->getKey();
            }
        }

        if (!isset($processes['C'], $processes['S'])) {
            throw new RuntimeException('Słownik procesów nie ma cięcia (C) albo szlifu (S).');
        }

        $statuses = [];

        foreach (array_keys(self::STATUS_WEIGHTS) as $code) {
            $status = Status::findByCode(StatusDomain::ORDER, $code);

            if ($status === null) {
                throw new RuntimeException('Katalog statusów nie zna ' . $code . '.');
            }

            $statuses[$code] = (int) $status->getKey();
        }

        return [
            'users' => $users,
            'contractors' => $contractors,
            'products' => $products,
            'pickups' => $pickups,
            'processes' => $processes,
            'statuses' => $statuses,
        ];
    }

    /**
     * @param array<int|string, mixed> $values
     * @return list<int>
     */
    private function ids(array $values): array
    {
        return array_values(array_map(static fn(mixed $id): int => (int) $id, $values));
    }

    /**
     * @param list<int> $values
     */
    private function pick(array $values): int
    {
        return $values[mt_rand(0, count($values) - 1)];
    }

    /**
     * @param array<string, int> $weights
     */
    private function weighted(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return (string) array_key_last($weights);
    }
}
