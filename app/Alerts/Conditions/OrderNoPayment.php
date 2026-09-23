<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Models\Order;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zlecenie bez ani złotówki wpłaty w statusie, w którym powinna już być.
 *
 * Suma, nie liczba wierszy: korekta jest wpłatą z kwotą ujemną, więc
 * zlecenie z wpłatą i jej stornem ma dwa wiersze i zero pieniędzy.
 *
 * ⚠️ Warunek nie pyta o **wysokość** zaliczki — próg zaliczki jest
 * warunkiem przejścia `ZLECENIE → PRODUKCJA` i mieszka w
 * `status_transitions`. Powtórzenie go tutaj dałoby dwa miejsca, które
 * rozjadą się przy pierwszej zmianie.
 */
final class OrderNoPayment extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_NO_PAYMENT;
    }

    public function label(): string
    {
        return 'Brak wpłaty';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::PAYMENTS;
    }

    public function parameters(): array
    {
        return [[
            'key' => 'statuses',
            'label' => 'Statusy, w których to alarmuje',
            'type' => 'statuses',
            'default' => ['PRODUKCJA'],
            'hint' => 'Domyślnie produkcja: zlecenie jest już w robocie, a kasa pusta.',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $codes = $this->statusCodes($params, ['PRODUKCJA']);

        /** @var list<int> $ids */
        $ids = Order::query()
            ->whereHas(
                'status',
                static fn(Builder $status): Builder => $status->whereIn('code', $codes),
            )
            ->whereRaw(
                '(select coalesce(sum(payments.amount_base), 0)'
                . ' from payments where payments.order_id = orders.id) <= 0',
            )
            ->pluck('orders.id')
            ->all();

        $found = [];

        foreach ($ids as $id) {
            $found[(int) $id] = null;
        }

        return $found;
    }
}
