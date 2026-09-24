<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Models\Order;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Orders\DrawingRequirement;

/**
 * Zlecenie bez oświadczenia o komplecie rysunków.
 *
 * Pytamy o `drawings_complete_at`, a nie o liczbę wgranych plików:
 * komplet to **oświadczenie konkretnej osoby z datą**, nie „są jakieś
 * pliki". Produkcja rusza na podstawie oświadczenia.
 *
 * **Tylko zlecenia, które mają co rysować** (`DrawingRequirement`):
 * kształt, owal albo proces z rysunkiem. Proste docinki nie zapalają
 * alertu i nie czekają na oświadczenie przy wejściu na produkcję.
 */
final class OrderMissingDrawings extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_MISSING_DRAWINGS;
    }

    public function label(): string
    {
        return 'Brak kompletu rysunków';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::MISSING_DATA;
    }

    public function parameters(): array
    {
        return [[
            'key' => 'statuses',
            'label' => 'Statusy, w których to blokuje',
            'type' => 'statuses',
            'default' => ['ZLECENIE'],
            'hint' => 'Przed przyjęciem zlecenia brak rysunków jest normalny — alert ma sens dopiero po akceptacji oferty.',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $codes = $this->statusCodes($params, ['ZLECENIE']);

        /** @var list<int> $ids */
        $ids = Order::query()
            ->whereHas(
                'status',
                static fn(Builder $status): Builder => $status->whereIn('code', $codes),
            )
            ->whereNull('orders.drawings_complete_at')
            // Tylko zlecenia, ktore maja co rysowac — ta sama regula, ktora
            // blokuje przejscie do produkcji.
            ->tap(static fn(Builder $orders): Builder => (new DrawingRequirement())->scope($orders))
            ->pluck('orders.id')
            ->all();

        $found = [];

        foreach ($ids as $id) {
            $found[(int) $id] = null;
        }

        return $found;
    }
}
