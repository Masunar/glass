<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;

/**
 * Minął umówiony termin kontaktu z klientem.
 *
 * `agreed_contact_on` jest dziś polem, które nikt nie czyta — data
 * wpisywana i niepilnowana. To dokładnie ta kolumna, o której wiadomo,
 * że istnieje, dopóki nie pojawi się pytanie, które jej potrzebuje.
 */
final class OrderContactOverdue extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_CONTACT_OVERDUE;
    }

    public function label(): string
    {
        return 'Umówiony kontakt po terminie';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::DEADLINE;
    }

    public function parameters(): array
    {
        return [[
            'key' => 'days',
            'label' => 'Dni po umówionej dacie',
            'type' => 'days',
            'default' => 1,
            'hint' => 'Liczone od dnia następnego po dacie kontaktu.',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $days = $this->days($params, 1, 1);
        $limit = $day->copy()->subDays($days)->toDateString();

        // Jak w `OrderOverdue`: porownanie dat w bazie, odejmowanie w PHP,
        // bo testy chodza na SQLite, a aplikacja na MariaDB. Eloquent
        // przepuszcza `pluck` przez rzutowania modelu, wiec wraca tu
        // Carbon, a nie napis — `Carbon::parse` przyjmie oba.
        /** @var array<int, \Carbon\Carbon|string|null> $rows */
        $rows = $this->open()
            ->whereNotNull('orders.agreed_contact_on')
            ->where('orders.agreed_contact_on', '<=', $limit)
            ->pluck('orders.agreed_contact_on', 'orders.id')
            ->all();

        $found = [];

        foreach ($rows as $id => $agreed) {
            if ($agreed === null) {
                continue;
            }

            $over = (int) Carbon::parse($agreed)->startOfDay()->diffInDays($day, false);
            $found[(int) $id] = (string) $over;
        }

        return $found;
    }
}
