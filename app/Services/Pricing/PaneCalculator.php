<?php

declare(strict_types=1);

namespace App\Services\Pricing;

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
        $minimum = $pane->isTempered
            ? $parameters->minBillableTemperedM2
            : $parameters->minBillableUntemperedM2;

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
            $cost = round($pane->runningMeters() * (float) $process['net_price_per_running_meter'], 2);
            $amount = round($amount + $cost, 2);

            $steps[] = new QuoteStep(
                'process',
                $process['label'],
                $this->money($amount),
                sprintf(
                    '+ %s zł (%s mb × %s zł/mb)',
                    $this->money($cost),
                    number_format($pane->runningMeters(), 2, ',', ' '),
                    $process['net_price_per_running_meter'],
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
