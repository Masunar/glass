<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;

/**
 * Zlecenie wstrzymane — flaga `is_on_hold` z powodem.
 *
 * Wstrzymanie jest flagą, nie statusem, właśnie po to, żeby nie gubić
 * informacji, gdzie zlecenie faktycznie stoi. Skutkiem ubocznym było to,
 * że **nigdzie się nie liczyło**: wstrzymane zlecenie wyglądało na
 * liście jak każde inne.
 */
final class OrderOnHold extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_ON_HOLD;
    }

    public function label(): string
    {
        return 'Zlecenie wstrzymane';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::MISSING_DATA;
    }

    public function parameters(): array
    {
        return [];
    }

    public function find(Carbon $day, array $params): array
    {
        /** @var array<int, string|null> $rows */
        $rows = $this->open()
            ->where('orders.is_on_hold', true)
            ->pluck('orders.hold_reason', 'orders.id')
            ->all();

        $found = [];

        foreach ($rows as $id => $reason) {
            $found[(int) $id] = $reason === null || trim($reason) === '' ? null : trim($reason);
        }

        return $found;
    }
}
