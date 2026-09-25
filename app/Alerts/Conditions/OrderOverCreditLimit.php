<?php

declare(strict_types=1);

namespace App\Alerts\Conditions;

use Carbon\Carbon;
use App\Models\Contractor;
use App\Enum\AlertCategory;
use App\Enum\AlertConditionType;
use App\Services\Orders\ContractorBalance;
use Illuminate\Database\Eloquent\Builder;

/**
 * Zlecenie w toku u kontrahenta ponad limitem kupieckim.
 *
 * Blokada przejścia do produkcji łapie zlecenie w jednym punkcie. Ta
 * reguła pokazuje stan: także zlecenia przepchnięte zgodą administratora
 * i te, które przeszły, zanim dług urósł. Dług liczy `ContractorBalance`
 * — ta sama definicja co na karcie i w warunku przejścia.
 *
 * Wartością jest kwota ponad limitem: rośnie, gdy robi się gorzej, więc
 * odhaczenie wraca samo.
 */
final class OrderOverCreditLimit extends OrderCondition
{
    public function type(): AlertConditionType
    {
        return AlertConditionType::ORDER_OVER_CREDIT_LIMIT;
    }

    public function label(): string
    {
        return 'Ponad limitem kupieckim';
    }

    public function category(): AlertCategory
    {
        return AlertCategory::PAYMENTS;
    }

    public function parameters(): array
    {
        return [[
            'key' => 'statuses',
            'label' => 'Statusy',
            'type' => 'statuses',
            'default' => ['ZLECENIE', 'PRODUKCJA'],
            'hint' => 'Domyślnie od przyjęcia do końca produkcji — tam jeszcze można zatrzymać pracę.',
        ]];
    }

    public function find(Carbon $day, array $params): array
    {
        $codes = $this->statusCodes($params, ['ZLECENIE', 'PRODUKCJA']);

        /** @var array<int, mixed> $orders */
        $orders = $this->open()
            ->whereHas(
                'status',
                static fn(Builder $status): Builder => $status->whereIn('code', $codes),
            )
            ->whereNotNull('orders.contractor_id')
            ->pluck('orders.contractor_id', 'orders.id')
            ->all();

        if ($orders === []) {
            return [];
        }

        $contractorIds = array_values(array_unique(array_map(intval(...), $orders)));

        /** @var iterable<Contractor> $contractors */
        $contractors = Contractor::query()->whereIn('id', $contractorIds)->get();

        $balance = new ContractorBalance();
        $balance->preload($contractorIds);

        $over = [];

        foreach ($contractors as $contractor) {
            $excess = (float) $balance->outstanding($contractor) - (float) $contractor->credit_limit;

            if ($excess > 0.0) {
                $over[(int) $contractor->getKey()] = number_format($excess, 2, '.', '');
            }
        }

        $found = [];

        foreach ($orders as $id => $contractorId) {
            $key = (int) $contractorId;

            if (isset($over[$key])) {
                $found[(int) $id] = $over[$key];
            }
        }

        return $found;
    }
}
