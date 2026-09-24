<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\ProductionStatus;
use App\Models\ProductionTask;

/**
 * Jak daleko jest zlecenie — w pieniądzach i na hali.
 *
 * Jedno miejsce dla obu procentów, bo pokazują je trzy ekrany: lista
 * zleceń, kolejka produkcji i karta. Każdy z nich liczący po swojemu
 * to trzy liczby, które rozjadą się przy pierwszej zmianie reguły.
 *
 * **Procent zaokrąglamy w dół.** 199 etapów z 200 to 99%, nie 100% —
 * sto procent ma znaczyć „skończone", a nie „prawie". To samo przy
 * wpłatach: brak kilku złotych to jeszcze nie komplet.
 */
final readonly class OrderProgress
{
    /**
     * Procent wpłat od brutto. Bez znanego brutto (zlecenie bez typu
     * faktury) procentu nie ma — dzielenie przez netto twierdziłoby,
     * że klient zapłacił więcej, niż zapłacił.
     */
    public static function paidPercent(float $paid, ?float $gross): ?int
    {
        if ($gross === null || $gross <= 0.0) {
            return null;
        }

        return self::percent($paid, $gross);
    }

    /**
     * Postęp produkcji zleceń: wykonane etapy przez wszystkie etapy.
     *
     * Liczone są **wszystkie** etapy zlecenia, także podzlecane — te
     * same, od których zależy przejście na „Gotowe". Zlecenie bez
     * etapów (jeszcze przed produkcją) nie ma wpisu: 0% znaczyłoby
     * „stoi", a ono jeszcze nie ruszyło.
     *
     * Jedno zapytanie dla całej strony, nie jedno na wiersz.
     *
     * @param array<int|string, mixed> $orderIds
     * @return array<int, array{done: int, total: int, percent: int}>
     */
    public function production(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = ProductionTask::query()
            ->toBase()
            ->selectRaw(
                'order_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done',
                [ProductionStatus::DONE->value],
            )
            ->whereIn('order_id', array_values($orderIds))
            ->groupBy('order_id')
            ->get();

        $progress = [];

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $done = (int) $row->done;

            $progress[(int) $row->order_id] = [
                'done' => $done,
                'total' => $total,
                'percent' => self::percent($done, $total),
            ];
        }

        return $progress;
    }

    private static function percent(float|int $part, float|int $whole): int
    {
        // Male epsilon: suma groszy w liczbach zmiennoprzecinkowych bywa
        // o wlos mniejsza od brutto (99.9999999), a floor zrobilby
        // z pelnej wplaty 99%.
        return (int) floor($part / $whole * 100 + 1e-9);
    }
}
