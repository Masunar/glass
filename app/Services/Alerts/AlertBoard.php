<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use Carbon\Carbon;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * Odczyt alertów: znaczniki w wierszach, liczniki przy zakładkach,
 * pasmo na pulpicie.
 *
 * Alert dotyczy zlecenia, więc **widzi go ten, kto widzi zlecenie** —
 * nie osobne uprawnienie. Uprawnienie `alerts` pilnuje wyłącznie ekranu
 * reguł, bo tam zmienia się konfiguracja, a nie ogląda dane.
 */
final readonly class AlertBoard
{
    public function __construct(
        private AlertEngine $engine = new AlertEngine(),
    ) {
    }

    /**
     * Alerty zleceń w postaci gotowej dla listy: id zlecenia => znaczniki.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function forOrders(?Carbon $day = null): array
    {
        $byOrder = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['alertable_type'] !== Order::class) {
                continue;
            }

            $id = (int) $row['alertable_id'];

            $byOrder[$id][] = [
                'code' => $row['code'],
                'label' => $row['label'],
                'color' => $row['color'],
                'category' => $row['category'],
                'value' => $row['value'],
                'since' => $row['since'],
            ];
        }

        return $byOrder;
    }

    /**
     * Czerwone liczniki przy zakładkach statusów.
     *
     * Kluczem jest kod statusu, a `null` to zakładka „Wszystkie”. Licznik
     * liczy **zlecenia, nie alerty**: zlecenie z trzema problemami to
     * jedna sprawa do ruszenia, nie trzy.
     *
     * @return array<string, int> kod statusu => liczba zleceń, plus klucz '' dla całości
     */
    public function orderCounts(?Carbon $day = null): array
    {
        $ids = array_keys($this->forOrders($day));

        if ($ids === []) {
            return ['' => 0];
        }

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->with('status')
            ->whereIn('id', $ids)
            ->get();

        $counts = ['' => $orders->count()];

        foreach ($orders as $order) {
            $code = $order->status?->code;

            if ($code === null) {
                continue;
            }

            $counts[$code] = ($counts[$code] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Pasmo alertów na pulpicie: reguła, ile razy zapalona, dokąd idzie.
     *
     * @return list<array<string, mixed>>
     */
    public function summary(?Carbon $day = null): array
    {
        /** @var array<string, int> $counts */
        $counts = [];
        /** @var array<string, array<string, mixed>> $meta */
        $meta = [];

        foreach ($this->engine->run($day) as $row) {
            $code = (string) $row['code'];

            $counts[$code] = ($counts[$code] ?? 0) + 1;
            $meta[$code] ??= [
                'code' => $code,
                'name' => $row['name'],
                'label' => $row['label'],
                'color' => $row['color'],
                'category' => $row['category'],
                'module' => $row['module'],
            ];
        }

        $rows = [];

        foreach ($meta as $code => $row) {
            $row['count'] = $counts[$code] ?? 0;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Numery zleceń objętych regułą — do wyświetlenia pod etykietą.
     *
     * @return list<array{id: int, number: int, value: string|null}>
     */
    public function ordersFor(string $code, int $limit = 5, ?Carbon $day = null): array
    {
        $matched = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['code'] !== $code || $row['alertable_type'] !== Order::class) {
                continue;
            }

            $matched[(int) $row['alertable_id']] = $row['value'];
        }

        if ($matched === []) {
            return [];
        }

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->whereIn('id', array_keys($matched))
            ->orderBy('number')
            ->limit($limit)
            ->get();

        $rows = [];

        foreach ($orders as $order) {
            $id = (int) $order->getKey();

            $rows[] = [
                'id' => $id,
                'number' => (int) $order->number,
                'value' => $matched[$id] ?? null,
            ];
        }

        return $rows;
    }
}
