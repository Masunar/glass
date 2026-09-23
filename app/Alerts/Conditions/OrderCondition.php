<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use App\Models\Order;
use App\Alerts\AlertCondition;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wspólna część warunków dotyczących zlecenia.
 *
 * Zlecenie w statusie końcowym nie ma czego pilnować — inaczej archiwum
 * zalałoby każdy licznik. To ta sama zasada, co w paśmie „Zaległe" na
 * liście (`OrderBoardService::bandFor()`), i nie może być powtórzona
 * inaczej w dwóch miejscach.
 */
abstract class OrderCondition implements AlertCondition
{
    public function alertable(): string
    {
        return Order::class;
    }

    public function module(): string
    {
        return 'zlec';
    }

    /** @return Builder<Order> */
    protected function open(): Builder
    {
        return Order::query()->whereHas(
            'status',
            static fn(Builder $status): Builder => $status->where('is_final', false),
        );
    }

    /**
     * Kody statusów z parametru reguły, z listą zapasową typu.
     *
     * @param array<string, mixed> $params
     * @param list<string> $fallback
     * @return list<string>
     */
    protected function statusCodes(array $params, array $fallback): array
    {
        $codes = $params['statuses'] ?? null;

        if (!is_array($codes)) {
            return $fallback;
        }

        $clean = [];

        foreach ($codes as $code) {
            if (is_string($code) && trim($code) !== '') {
                $clean[] = trim($code);
            }
        }

        // Pusta lista statusow znaczy „nie wybrano", a nie „zaden" —
        // regula bez ani jednego statusu nie odpalilaby sie nigdy
        // i wygladalaby na dzialajaca.
        return $clean === [] ? $fallback : $clean;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function days(array $params, int $fallback, int $min = 0): int
    {
        $value = $params['days'] ?? null;

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return $fallback;
        }

        return max($min, (int) $value);
    }
}
