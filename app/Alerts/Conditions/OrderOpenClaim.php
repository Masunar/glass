<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Models\Order;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;

/**
 * Otwarta reklamacja na zleceniu.
 *
 * Jedyny warunek, który **nie wyklucza statusów końcowych**: reklamacja
 * przychodzi najczęściej po rozliczeniu, a zlecenie zamknięte z otwartą
 * reklamacją jest dokładnie tym, czego nie wolno stracić z oczu.
 */
final class OrderOpenClaim extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_OPEN_CLAIM;
    }

    public function label(): string
    {
        return 'Otwarta reklamacja';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::QUALITY;
    }

    public function parameters(): array
    {
        return [];
    }

    public function find(Carbon $day, array $params): array
    {
        /** @var list<int> $ids */
        $ids = Order::query()
            ->where('orders.has_open_claim', true)
            ->pluck('orders.id')
            ->all();

        $found = [];

        foreach ($ids as $id) {
            $found[(int) $id] = null;
        }

        return $found;
    }
}
