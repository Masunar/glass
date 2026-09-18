<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enum\Unit;
use App\DTO\Pricing\Quote;
use App\Enum\MinPriceCheck;
use App\Enum\SurchargeMode;
use App\DTO\Pricing\QuoteStep;
use App\DTO\Pricing\PaneSpecification;
use App\DTO\Pricing\PricingParameters;

/**
 * Wycena formatki szkła.
 *
 * Czysta funkcja: dostaje specyfikację, ceny jednostkowe i parametry,
 * a zwraca kwotę wraz ze ścieżką wyliczenia. Nie sięga do bazy, więc
 * daje się przetestować na udokumentowanych wartościach i użyć zarówno
 * przy wycenie bieżącej, jak i przy odtwarzaniu ceny historycznej.
 *
 * Wzór odtworzony z dokumentacji:
 *
 *   m² rozliczeniowe = max(szer × wys × ilość, minimum)
 *   cena             = m² rozliczeniowe × cena_m²
 *   kształt nieregularny → dopłata
 *   powierzchnia formatki powyżej progu → dopłata za gabaryt
 *   cena poniżej progu → dopłata do minimum
 *   + Σ procesów (metry bieżące × cena procesu)
 *
 * Dwie rzeczy są parametrami, a nie decyzjami w kodzie, bo dokumentacja
 * ich nie rozstrzyga: sposób łączenia dopłat (S-03) i moment sprawdzenia
 * progu minimalnej ceny (S-04).
 */
final readonly class PaneCalculator
{
    /**
     * @param string $netPricePerSquareMeter cena sprzedaży m² dla materiału i sekcji cenowej
     * @param list<array{label: string, net_price_per_running_meter: string}> $processes
     */
    public function calculate(
        PaneSpecification $pane,
        string $netPricePerSquareMeter,
        PricingParameters $parameters,
        array $processes = [],
    ): Quote {
        $steps = [];

        $rawSquareMeters = round($pane->squareMeters(), 4);
        // Wyjatek wpisany przy formatce wygrywa z parametrem globalnym.
        // Zero jest prawidlowa odpowiedzia — „rozlicz doslownie tyle, ile
        // jest" — wiec nie traktujemy go jak braku.
        $minimum = $pane->minBillableM2 ?? ($pane->isTempered
            ? $parameters->minBillableTemperedM2
            : $parameters->minBillableUntemperedM2);

        $billable = max($rawSquareMeters, $minimum);

        $steps[] = new QuoteStep(
            'area',
            'Powierzchnia rozliczeniowa',
            $this->formatArea($billable),
            $billable > $rawSquareMeters
                ? sprintf(
                    'podniesiona z %s m² do minimum %s m² (%s)',
                    $this->formatArea($rawSquareMeters),
                    $this->formatArea($minimum),
                    $pane->isTempered ? 'formatka hartowana' : 'formatka niehartowana',
                )
                : sprintf('%d × %d mm × %d szt.', $pane->widthMm, $pane->heightMm, $pane->quantity),
        );

        $amount = round($billable * (float) $netPricePerSquareMeter, 2);

        $steps[] = new QuoteStep(
            'base',
            'Cena materiału',
            $this->money($amount),
            sprintf('%s m² × %s zł/m²', $this->formatArea($billable), $netPricePerSquareMeter),
        );

        $amount = $this->applySurcharges($pane, $amount, $parameters, $steps);

        foreach ($processes as $process) {
            $unit = $process['unit'] ?? Unit::RUNNING_METER;
            $cost = (float) $this->processAmount($pane, $process['unit_net_price'], $unit);
            $amount = round($amount + $cost, 2);
            $label = $this->unitLabel($unit);

            $steps[] = new QuoteStep(
                'process',
                $process['label'],
                $this->money($amount),
                sprintf(
                    '+ %s zł (%s %s × %s zł/%s)',
                    $this->money($cost),
                    number_format($this->units($pane, $unit), 2, ',', ' '),
                    $label,
                    $process['unit_net_price'],
                    $label,
                ),
            );
        }

        return new Quote(
            net: $this->money($amount),
            steps: $steps,
            billableSquareMeters: $billable,
            runningMeters: round($pane->runningMeters(), 2),
        );
    }

    /**
     * Kwota jednego procesu: metry bieżące × cena za mb.
     *
     * Publiczna, bo na zleceniu procesy są osobnymi wierszami i kwota
     * każdego musi zostać zapisana z osobna. Wzór ma jednak zostać
     * w jednym miejscu — inaczej wycena pozycji i suma zlecenia zaczną
     * się różnić o grosze, a nikt nie będzie wiedział, która jest
     * prawdziwa.
     */
    /**
     * Kwota procesu — cena razy **jego własna jednostka**.
     *
     * Do tej pory każdy proces liczył się od metrów bieżących, bo tak
     * liczy się obróbka krawędzi i od niej zaczynaliśmy. Hartownia
     * rozlicza się od metra kwadratowego, a CNC od sztuki: przy szybie
     * 1,5 × 1,0 m to pięć metrów bieżących, więc CNC za 150 zł
     * wystawiało 750. Jednostka stoi w słowniku przy pozycji cennikowej
     * i wystarczyło ją przeczytać.
     */
    public function processAmount(
        PaneSpecification $pane,
        string $unitNetPrice,
        Unit $unit = Unit::RUNNING_METER,
    ): string {
        return $this->money(round($this->units($pane, $unit) * (float) $unitNetPrice, 2));
    }

    /** Ile jednostek tego rodzaju niesie ta formatka. */
    public function units(PaneSpecification $pane, Unit $unit): float
    {
        return match ($unit) {
            Unit::RUNNING_METER => $pane->runningMeters(),
            // Powierzchnia surowa, nie rozliczeniowa: minimalna
            // powierzchnia z parametrów dotyczy ceny materiału i nikt
            // nie powiedział, że obowiązuje też obróbkę.
            Unit::SQUARE_METER => $pane->squareMeters(),
            Unit::PIECE => (float) $pane->quantity,
        };
    }

    /** Skrót jednostki do pokazania przy formule. */
    public function unitLabel(Unit $unit): string
    {
        return match ($unit) {
            Unit::RUNNING_METER => 'mb',
            Unit::SQUARE_METER => 'm²',
            Unit::PIECE => 'szt.',
        };
    }

    /** @param list<QuoteStep> $steps */
    private function applySurcharges(
        PaneSpecification $pane,
        float $amount,
        PricingParameters $parameters,
        array &$steps,
    ): float {
        if ($parameters->minPriceCheck === MinPriceCheck::BEFORE_SURCHARGES) {
            $amount = $this->applyMinimumPrice($amount, $parameters, $steps);
        }

        /** @var list<array{code: string, label: string, percent: float}> $applicable */
        $applicable = [];

        if ($pane->isIrregularShape && $parameters->shapeSurchargePercent > 0) {
            $applicable[] = [
                'code' => 'shape',
                'label' => 'Dopłata za nieregularny kształt',
                'percent' => $parameters->shapeSurchargePercent,
            ];
        }

        if ($pane->paneSquareMeters() > $parameters->oversizeThresholdM2
            && $parameters->oversizeSurchargePercent > 0) {
            $applicable[] = [
                'code' => 'oversize',
                'label' => 'Dopłata za gabaryt',
                'percent' => $parameters->oversizeSurchargePercent,
            ];
        }

        if ($applicable !== [] && $parameters->surchargeMode === SurchargeMode::HIGHEST_ONLY) {
            usort($applicable, static fn(array $a, array $b): int => $b['percent'] <=> $a['percent']);
            $applicable = [$applicable[0]];
        }

        foreach ($applicable as $surcharge) {
            $amount = round($amount * (1 + $surcharge['percent'] / 100), 2);

            $steps[] = new QuoteStep(
                $surcharge['code'],
                $surcharge['label'],
                $this->money($amount),
                sprintf('+ %s%%', $this->percent($surcharge['percent'])),
            );
        }

        if ($parameters->minPriceCheck === MinPriceCheck::AFTER_SURCHARGES) {
            $amount = $this->applyMinimumPrice($amount, $parameters, $steps);
        }

        return $amount;
    }

    /** @param list<QuoteStep> $steps */
    private function applyMinimumPrice(float $amount, PricingParameters $parameters, array &$steps): float
    {
        if ($amount >= $parameters->minPanePrice || $parameters->minPaneSurchargePercent <= 0) {
            return $amount;
        }

        $raised = round($amount * (1 + $parameters->minPaneSurchargePercent / 100), 2);

        $steps[] = new QuoteStep(
            'min_price',
            'Dopłata do minimalnej wartości formatki',
            $this->money($raised),
            sprintf(
                '+ %s%% — cena %s zł poniżej progu %s zł',
                $this->percent($parameters->minPaneSurchargePercent),
                $this->money($amount),
                $this->money($parameters->minPanePrice),
            ),
        );

        return $raised;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function formatArea(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
