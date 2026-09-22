<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\GlobalParameter;
use App\DTO\Orders\InvestmentVat;

/**
 * Proporcja stawki obniżonej dla inwestycji mieszkaniowej.
 *
 * Stawka 8 % w budownictwie objętym społecznym programem mieszkaniowym
 * ma limit powierzchni użytkowej (art. 41 ust. 12b ustawy o VAT):
 * dom jednorodzinny do 300 m², lokal mieszkalny do 150 m². Po
 * przekroczeniu limitu stawka obniżona **nie znika** — obejmuje tylko
 * tę część podstawy, która odpowiada udziałowi metrażu mieszczącego
 * się w limicie (ust. 12c). Dom 500 m²: 300/500 = 60 % kwoty na 8 %,
 * 40 % na 23 %.
 *
 * Limity i stawki są parametrami słownika, nie stałymi w kodzie. To
 * liczby ustawowe: zmieniały się i zmienią znowu, a wtedy poprawka ma
 * być wpisem w słowniku, nie wdrożeniem.
 *
 * **Brak metrażu nie jest zerem i nie jest całością.** Bez powierzchni
 * inwestycji proporcji nie da się policzyć, więc `share` zostaje
 * `null`, a wyżej kwota netto ląduje w koszyku „stawka nieznana".
 * Zlecenie pokazuje wtedy netto i mówi, czego brakuje — zamiast podać
 * brutto, które byłoby zmyślone.
 */
final readonly class VatSplit
{
    /**
     * Podział dla zlecenia albo `null`, gdy nie dotyczy.
     *
     * `null` znaczy zwykłą sprzedaż: zlecenie nie ma wskazanego rodzaju
     * inwestycji, więc każda lista zostaje przy swojej stawce.
     */
    public function for(Order $order, ?Carbon $on = null): ?InvestmentVat
    {
        $type = $order->investment_type;

        if ($type === null) {
            return null;
        }

        $reduced = GlobalParameter::number('vat_reduced_rate', $on);
        $standard = GlobalParameter::number('vat_standard_rate', $on);

        // Bez stawek nie wiemy nawet, ktorej listy to dotyczy. To nie
        // jest stan danych, tylko zepsuty slownik — nie udajemy podzialu.
        if ($reduced === null || $standard === null) {
            return null;
        }

        $limit = GlobalParameter::number($type->limitParameter(), $on);
        $area = $order->investment_area_m2 === null ? null : (float) $order->investment_area_m2;

        [$share, $reason] = $this->share($limit, $area);

        return new InvestmentVat(
            type: $type,
            reducedRate: (int) round($reduced),
            standardRate: (int) round($standard),
            limitM2: $limit,
            areaM2: $area,
            share: $share,
            reason: $reason,
        );
    }

    /**
     * @return array{0: float|null, 1: string|null}
     */
    private function share(?float $limit, ?float $area): array
    {
        if ($limit === null || $limit <= 0.0) {
            return [null, 'Brak limitu powierzchni w słowniku parametrów.'];
        }

        if ($area === null || $area <= 0.0) {
            return [null, 'Brak powierzchni użytkowej inwestycji — bez niej nie da się policzyć proporcji.'];
        }

        if ($area <= $limit) {
            return [1.0, null];
        }

        return [$limit / $area, null];
    }
}
