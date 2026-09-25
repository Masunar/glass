<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wycena, której nie ma: zlecenie bez żadnej pozycji albo z pozycją bez
 * ceny (np. materiał spoza cennika kontrahenta — „bez ceny szkła").
 *
 * Tylko listy wliczone: wariant odrzucony nie pójdzie na ofertę. Wartością
 * jest liczba pozycji bez ceny; pusta wycena ma wartość pustą, bo nie ma
 * czego liczyć.
 */
final class OrderUnpriced extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_UNPRICED;
    }

    public function label(): string
    {
        return 'Brak wyceny';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::MISSING_DATA;
    }

    public function parameters(): array
    {
        return [[
            'key' => 'statuses',
            'label' => 'Statusy',
            'type' => 'statuses',
            'default' => ['DO_WYCENY'],
            'hint' => 'Domyślnie wycena: po przyjęciu zlecenia pozycja bez ceny to już inny problem.',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $codes = $this->statusCodes($params, ['DO_WYCENY']);

        $scope = fn(): Builder => $this->open()->whereHas(
            'status',
            static fn(Builder $status): Builder => $status->whereIn('code', $codes),
        );

        $included = static fn(Builder $list): Builder => $list->where('is_included', true);

        $found = [];

        // Pusta wycena: ani jednej pozycji na liscie wliczonej.
        /** @var list<int> $empty */
        $empty = $scope()
            ->whereDoesntHave('lists', static fn(Builder $list): Builder => $included($list)->has('items'))
            ->pluck('orders.id')
            ->all();

        foreach ($empty as $id) {
            $found[(int) $id] = null;
        }

        // Pozycje bez ceny — policzone, ile ich jest na zleceniu.
        /** @var array<int, mixed> $counts */
        $counts = $scope()
            ->join('order_lists', 'order_lists.order_id', '=', 'orders.id')
            ->join('order_items', 'order_items.order_list_id', '=', 'order_lists.id')
            ->where('order_lists.is_included', true)
            ->whereNull('order_items.unit_net_price')
            ->groupBy('orders.id')
            ->selectRaw('orders.id as id, COUNT(order_items.id) as missing')
            ->pluck('missing', 'id')
            ->all();

        foreach ($counts as $id => $missing) {
            $found[(int) $id] = (string) (int) $missing;
        }

        return $found;
    }
}
