<?php

declare(strict_types=1);

namespace App\Services\Alerts;

use Carbon\Carbon;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Odczyt alertów: znaczniki w wierszach, liczniki przy zakładkach,
 * pasmo na pulpicie.
 *
 * Alert dotyczy zlecenia, więc **widzi go ten, kto widzi zlecenie** —
 * nie osobne uprawnienie. Uprawnienie `alerts` pilnuje wyłącznie ekranu
 * reguł, bo tam zmienia się konfiguracja, a nie ogląda dane.
 *
 * **Odhaczony alert znika z liczników, ale zostaje w wierszu.** Licznik
 * odpowiada na pytanie „ile wymaga reakcji", a znacznik przy zleceniu na
 * „co z nim jest" — to dwa różne pytania. Gdyby odhaczenie zdejmowało
 * znacznik, sprawa znikałaby z oczu zamiast przestać krzyczeć.
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
                'occurrence_id' => $row['occurrence_id'],
                'acknowledged' => $row['acknowledged'],
                'acknowledged_at' => $row['acknowledged_at'],
                'acknowledged_by' => $row['acknowledged_by'],
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
     * `$ownerId` zawęża licznik do zleceń jednej osoby — lista z filtrem
     * „moje" nad czerwoną liczbą liczoną z całości pokazywałaby problemy,
     * których w widocznych wierszach nie ma.
     *
     * @return array<string, int> kod statusu => liczba zleceń, plus klucz '' dla całości
     */
    public function orderCounts(?Carbon $day = null, ?int $ownerId = null): array
    {
        $ids = [];

        foreach ($this->forOrders($day) as $id => $marks) {
            foreach ($marks as $mark) {
                if ($mark['acknowledged'] !== true) {
                    $ids[] = $id;

                    break;
                }
            }
        }

        if ($ids === []) {
            return ['' => 0];
        }

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->with('status')
            ->whereIn('id', $ids)
            ->when(
                $ownerId !== null,
                static fn(Builder $builder): Builder => $builder->where('owner_id', $ownerId),
            )
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
            if ($row['acknowledged'] === true) {
                continue;
            }

            $code = (string) $row['code'];

            $counts[$code] = ($counts[$code] ?? 0) + 1;
            $meta[$code] ??= [
                'code' => $code,
                'name' => $row['name'],
                'label' => $row['label'],
                'color' => $row['color'],
                'category' => $row['category'],
                'module' => $row['module'],
                'resource' => $row['resource'],
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
     * Rzeczy objęte regułą — podpis i dokąd prowadzi.
     *
     * Nie „numery zleceń": reguła może dotyczyć produktu albo partii
     * w piecu, a pasmo alertów ma je nazwać tak samo. Podpis składa
     * warunek, bo tam już stoi zapytanie o tę tabelę.
     *
     * @return list<array{label: string, path: string|null, value: string|null}>
     */
    public function subjectsFor(string $code, int $limit = 5, ?Carbon $day = null): array
    {
        $rows = [];

        foreach ($this->engine->run($day) as $row) {
            if ($row['code'] !== $code || $row['acknowledged'] === true) {
                continue;
            }

            if ($row['subject_label'] === null) {
                continue;
            }

            $rows[] = [
                'label' => (string) $row['subject_label'],
                'path' => $row['subject_path'],
                'value' => $row['value'],
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }
}
