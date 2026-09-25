<?php

declare(strict_types=1);

namespace App\Alerts;

use App\Alerts\Conditions\OrderOnHold;
use App\Alerts\Conditions\OrderUnpriced;
use App\Alerts\Conditions\OrderStuckInStatus;
use App\Alerts\Conditions\OrderOverCreditLimit;
use App\Alerts\Conditions\OrderOverdue;
use App\Alerts\Conditions\OrderNoPayment;
use App\Alerts\Conditions\OrderOpenClaim;
use App\Alerts\Conditions\OrderContactOverdue;
use App\Alerts\Conditions\OrderMissingDrawings;
use App\Alerts\Conditions\StockBelowMinimum;
use App\Alerts\Conditions\TemperingBatchLate;

/**
 * Katalog warunków: typ z enuma → klasa, która go liczy.
 *
 * Katalog jest **jedynym miejscem, w którym typ staje się kodem**.
 * Reguła w bazie trzyma nazwę typu; gdyby nazwa przestała cokolwiek
 * znaczyć — bo klasę usunięto albo przemianowano — reguła nadal
 * wyglądałaby na aktywną i po cichu nic by nie robiła. Dlatego
 * `AlertCoverageTest` pilnuje, że **każdy wariant enuma ma tu klasę**.
 */
final readonly class ConditionCatalog
{
    /** @return array<string, AlertCondition> */
    public function all(): array
    {
        $conditions = [
            new OrderOverdue(),
            new OrderContactOverdue(),
            new OrderMissingDrawings(),
            new OrderNoPayment(),
            new OrderOnHold(),
            new OrderOpenClaim(),
            new OrderStuckInStatus(),
            new OrderUnpriced(),
            new OrderOverCreditLimit(),
            new StockBelowMinimum(),
            new TemperingBatchLate(),
        ];

        $map = [];

        foreach ($conditions as $condition) {
            $map[$condition->type()->value] = $condition;
        }

        return $map;
    }

    public function find(string $type): ?AlertCondition
    {
        return $this->all()[$type] ?? null;
    }

    /**
     * Domyślne parametry typu — prosto z jego własnej deklaracji.
     *
     * @return array<string, mixed>
     */
    public function defaults(AlertCondition $condition): array
    {
        $defaults = [];

        foreach ($condition->parameters() as $parameter) {
            $defaults[$parameter['key']] = $parameter['default'];
        }

        return $defaults;
    }

    /**
     * Parametry z formularza przycięte do tego, co typ w ogóle zna.
     *
     * Klucz spoza deklaracji typu znika — inaczej w `condition` osiadłby
     * parametr, którego nikt nie czyta, i wygladalby na ustawienie.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function normalize(AlertCondition $condition, array $params): array
    {
        $clean = ['type' => $condition->type()->value];

        foreach ($condition->parameters() as $parameter) {
            $key = $parameter['key'];
            $value = $params[$key] ?? $parameter['default'];

            $clean[$key] = match ($parameter['type']) {
                'days' => max(1, (int) (is_scalar($value) ? $value : 0)),
                'statuses' => $this->codes($value, $parameter['default']),
                default => $value,
            };
        }

        return $clean;
    }

    /**
     * @param int|string|list<string> $fallback
     * @return list<string>
     */
    private function codes(mixed $value, int|string|array $fallback): array
    {
        $codes = [];

        if (is_array($value)) {
            foreach ($value as $code) {
                if (is_string($code) && trim($code) !== '') {
                    $codes[] = trim($code);
                }
            }
        }

        if ($codes !== []) {
            return $codes;
        }

        return is_array($fallback) ? $fallback : [];
    }

    /**
     * Katalog dla ekranu reguł: co administrator ma do wyboru.
     *
     * @return list<array<string, mixed>>
     */
    public function board(): array
    {
        $rows = [];

        foreach ($this->all() as $type => $condition) {
            $rows[] = [
                'type' => $type,
                'label' => $condition->label(),
                'category' => $condition->category()->value,
                'module' => $condition->module(),
                'parameters' => $condition->parameters(),
            ];
        }

        return $rows;
    }
}
