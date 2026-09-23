<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Enum\AlertCategory;
use App\Alerts\AlertCondition;
use App\Enum\AlertConditionType;
use App\Models\TemperingBatch;
use App\Enum\TemperingBatchStatus;

/**
 * Partia wysłana do hartowni, która nie wróciła na czas.
 *
 * Do tej pory szyba potrafiła stać w piecu tygodniami i **nikt się o tym
 * nie dowiadywał**: kolejka pokazywała, że pozycja jest wysłana, a hala
 * czekała bez wyjaśnienia. To ten sam brak, co w starym systemie,
 * tylko w nowym schemacie.
 *
 * **Bez planowanego powrotu nie ma spóźnienia.** `expected_at` jest
 * nullowalne, a partia bez daty powrotu nie jest opóźniona — jest
 * partią, której nikt nie umówił. Zgadywanie terminu z dnia wysyłki
 * byłoby wymyślaniem daty, której nikt nie uzgodnił z hartownią.
 */
final class TemperingBatchLate implements AlertCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::TEMPERING_BATCH_LATE;
    }

    public function label(): string
    {
        return 'Partia nie wróciła z pieca';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::DEADLINE;
    }

    public function module(): string
    {
        return 'prod';
    }

    public function alertable(): string
    {
        return TemperingBatch::class;
    }

    public function resource(): string
    {
        return 'tempering';
    }

    public function parameters(): array
    {
        return [[
            'key' => 'days',
            'label' => 'Dni po umówionym powrocie',
            'type' => 'days',
            'default' => 1,
            'hint' => 'Liczone od daty powrotu ustalonej przy wysyłce. Partia bez tej daty nie alarmuje nigdy.',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $value = $params['days'] ?? null;
        $days = is_int($value) || (is_string($value) && ctype_digit($value)) ? max(1, (int) $value) : 1;
        $limit = $day->copy()->subDays($days)->toDateString();

        /** @var array<int, mixed> $rows */
        $rows = TemperingBatch::query()
            ->where('status', TemperingBatchStatus::SENT->value)
            ->whereNull('returned_at')
            ->whereNotNull('expected_at')
            ->where('expected_at', '<=', $limit)
            ->pluck('expected_at', 'id')
            ->all();

        $found = [];

        foreach ($rows as $id => $expected) {
            if ($expected === null) {
                continue;
            }

            // Odejmowanie dat w PHP, nie w SQL: testy chodza na SQLite,
            // aplikacja na MariaDB, a `DATEDIFF` istnieje tylko w tej
            // drugiej.
            $over = (int) Carbon::parse($expected)->startOfDay()->diffInDays($day, false);
            $found[(int) $id] = (string) $over;
        }

        return $found;
    }

    public function subjects(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var array<int, int> $numbers */
        $numbers = TemperingBatch::query()->whereIn('id', $ids)->pluck('number', 'id')->all();

        $rows = [];

        foreach ($numbers as $id => $number) {
            $rows[(int) $id] = [
                'label' => 'partia ' . (int) $number,
                'path' => '/hartownia',
            ];
        }

        return $rows;
    }
}
