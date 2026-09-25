<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zlecenie stoi w jednym statusie dłużej niż próg.
 *
 * Termin łapie zlecenia spóźnione wobec klienta; ta reguła łapie te,
 * które nie ruszają się w środku procesu — wycena bez odpowiedzi od
 * tygodni, zlecenie przyjęte i nieprzekazane na halę. Próg jest per
 * reguła, więc różne statusy dostają różne progi przez osobne reguły
 * tego samego typu.
 *
 * Liczy od `orders.status_changed_at`, ustawianego w modelu przy każdej
 * zmianie statusu. Wartością jest liczba dni w statusie — rośnie, więc
 * odhaczenie wraca samo, gdy sprawa dalej stoi.
 */
final class OrderStuckInStatus extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_STUCK;
    }

    public function label(): string
    {
        return 'Za długo w statusie';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::DEADLINE;
    }

    public function parameters(): array
    {
        return [
            [
                'key' => 'statuses',
                'label' => 'Statusy',
                'type' => 'statuses',
                'default' => ['ZLECENIE'],
                'hint' => 'Dla innego progu w innym statusie dodaj drugą regułę tego typu.',
            ],
            [
                'key' => 'days',
                'label' => 'Dni w statusie',
                'type' => 'days',
                'default' => 7,
                'hint' => 'Propozycja startowa, nie ustalenie — do przejrzenia.',
            ],
        ];
    }

    public function find(Carbon $day, array $params): array
    {
        $codes = $this->statusCodes($params, ['ZLECENIE']);
        $days = $this->days($params, 7, 1);
        // Koniec dnia progu: zlecenie z dzisiaj rano i z wczoraj wieczorem
        // to przy progu 1 ta sama sprawa, a nie roznica godzin.
        $limit = $day->copy()->subDays($days)->endOfDay();

        /** @var array<int, mixed> $rows */
        $rows = $this->open()
            ->whereHas(
                'status',
                static fn(Builder $status): Builder => $status->whereIn('code', $codes),
            )
            ->whereNotNull('orders.status_changed_at')
            ->where('orders.status_changed_at', '<=', $limit)
            ->pluck('orders.status_changed_at', 'orders.id')
            ->all();

        $found = [];

        foreach ($rows as $id => $since) {
            if ($since === null) {
                continue;
            }

            // Odejmowanie dat w PHP, nie w SQL: testy chodza na SQLite,
            // aplikacja na MariaDB.
            $found[(int) $id] = (string) (int) Carbon::parse($since)->startOfDay()->diffInDays($day, false);
        }

        return $found;
    }
}
