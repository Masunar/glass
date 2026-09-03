<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Status;
use App\Models\OrderList;
use App\Models\OrderItem;
use App\Enum\StatusDomain;
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
    ): array {
        $day = ($today ?? Carbon::today())->startOfDay();
        $needle = $query !== null ? trim($query) : '';

        $orders = Order::query()
            ->with([
                'contractor',
                'status',
                'pickupLocation',
                'creator',
                'lists.items.processes',
            ])
            ->when(
                $statusCode !== null && $statusCode !== '',
                static fn(Builder $builder): Builder => $builder->whereHas(
                    'status',
                    static fn(Builder $status): Builder => $status->where('code', $statusCode),
                ),
            )
            ->when(
                $needle !== '',
                fn(Builder $builder): Builder => $this->applySearch($builder, $needle),
            )
            ->orderByDesc('number')
            ->limit($limit)
            ->get();

        $bands = ['today' => [], 'overdue' => [], 'later' => []];

        foreach ($orders as $order) {
            $bands[$this->bandFor($order, $day)][] = $this->row($order, $day);
        }

        return [
            'bands' => [
                $this->band('today', $bands['today']),
                $this->band('overdue', $bands['overdue']),
                $this->band('later', $bands['later']),
            ],
            'filters' => $this->filters(),
            'summary' => [
                'today' => count($bands['today']),
                'overdue' => count($bands['overdue']),
                'shown' => $orders->count(),
                'as_of' => $day->toDateString(),
            ],
        ];
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

        if ($deadline === null || ($order->status?->is_final ?? false)) {
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
     * @return array<string, mixed>
     */
    private function row(Order $order, Carbon $day): array
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
            'delivery_place' => $order->pickupLocation?->name ?? $order->delivery_address,
            'amount' => $this->total($order),
            'owner_initials' => $this->initials($order),
            'is_on_hold' => (bool) $order->is_on_hold,
            'hold_reason' => $order->hold_reason,
            'has_open_claim' => (bool) $order->has_open_claim,
            'next_step' => $step?->toArray(),
            'blocked_step' => $blocked?->toArray(),
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

    private function total(Order $order): string
    {
        $total = 0.0;

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if (!$list->is_included) {
                continue;
            }

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $total += (float) $item->amount;

                foreach ($item->processes as $process) {
                    $total += (float) $process->amount;
                }
            }
        }

        return number_format($total, 2, '.', '');
    }

    private function initials(Order $order): ?string
    {
        $user = $order->creator;

        if ($user === null) {
            return null;
        }

        $initials = '';

        foreach (array_filter([$user->first_name, $user->last_name]) as $part) {
            $initials .= mb_strtoupper(mb_substr((string) $part, 0, 1));
        }

        return $initials === '' ? null : mb_substr($initials, 0, 2);
    }

    /**
     * Zakładki statusów z licznikami. Liczone po stronie bazy — lista
     * pokazuje najwyżej dwieście wierszy, a licznik ma mówić o całości.
     *
     * @return list<array<string, mixed>>
     */
    private function filters(): array
    {
        /** @var array<int, int> $counts */
        $counts = Order::query()
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

        $filters = [[
            'code' => null,
            'name' => 'Wszystkie',
            'count' => array_sum($counts),
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
            ];
        }

        return $filters;
    }
}
