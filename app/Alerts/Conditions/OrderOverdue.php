<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;

/**
 * Zlecenie po terminie o co najmniej N dni.
 *
 * Termin brany do porównania to **termin przesunięty, jeśli istnieje** —
 * tak samo, jak liczy go lista zleceń. Liczenie od `client_deadline`
 * przy uzgodnionym przesunięciu pokazywałoby opóźnienie, którego nie ma;
 * dokładnie to robił stary system.
 *
 * Zlecenie bez terminu nie jest spóźnione: `NULL <= data` nie jest
 * prawdą, więc odpada samo. **Brak terminu nie jest terminem zerowym.**
 */
final class OrderOverdue extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_OVERDUE;
    }

    public function label(): string
    {
        return 'Zlecenie po terminie';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::DEADLINE;
    }

    public function parameters(): array
    {
        return [[
            'key' => 'days',
            'label' => 'Dni po terminie',
            'type' => 'days',
            'default' => 1,
            'hint' => 'Alert zapala się, gdy termin minął o tyle dni. 1 znaczy „od pierwszego dnia po terminie".',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $days = $this->days($params, 1, 1);
        $limit = $day->copy()->subDays($days)->toDateString();

        // Roznice dni liczymy w PHP, nie w SQL. Testy chodza na SQLite,
        // aplikacja na MariaDB — `DATEDIFF` przechodzi analize statyczna
        // i wywala sie dopiero na tescie. Porownanie dat zostaje w bazie,
        // bo to ono zaweza wiersze; arytmetyka jest juz tylko na nich.
        /** @var array<int, string|null> $rows */
        $rows = $this->open()
            ->whereRaw('COALESCE(orders.shifted_deadline, orders.client_deadline) <= ?', [$limit])
            ->selectRaw(
                'orders.id as id,'
                . ' COALESCE(orders.shifted_deadline, orders.client_deadline) as due_on',
            )
            ->pluck('due_on', 'id')
            ->all();

        $found = [];

        foreach ($rows as $id => $due) {
            if ($due === null) {
                continue;
            }

            $over = (int) Carbon::parse($due)->startOfDay()->diffInDays($day, false);
            $found[(int) $id] = (string) $over;
        }

        return $found;
    }
}
