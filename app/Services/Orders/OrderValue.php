<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Enum\Section;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderList;
use App\Models\OrderDiscount;
use App\DTO\Orders\OrderTotals;

/**
 * Wartość zlecenia: sumy pozycji i rabat na zleceniu.
 *
 * Rabat to **czwarty i ostatni poziom ceny** (`10-zlecenia.md` §5.2):
 * katalog → sekcja cenowa kontrahenta → cena indywidualna → rabat na
 * zleceniu. Trzy pierwsze są zamrożone w pozycji w chwili wyceny;
 * czwarty nie, bo dotyczy całego zlecenia i zmienia się w negocjacjach.
 *
 * **Rabat nie zmienia kwot pozycji.** Gdyby zmieniał, każda zmiana
 * rabatu wymagałaby przeliczenia wszystkich formatek i kasowała ślad
 * wyceny, który jest jedyną odpowiedzią na pytanie „skąd ta cena".
 * Dlatego pozycja trzyma kwotę sprzed rabatu, a rabat jest osobną
 * pozycją podsumowania — tak samo jak na ofercie dla klienta.
 *
 * Rabaty per sekcja **nie sumują się procentowo**: 10 % na szkle i 5 %
 * na usługach to nie jest 15 % na zleceniu. Stąd rabat łączny wychodzi
 * wyłącznie kwotowo.
 */
final readonly class OrderValue
{
    public function totals(Order $order): OrderTotals
    {
        $percents = $this->percents($order);
        $bases = $this->bases($order);

        $sections = [];
        $base = 0.0;
        $discount = 0.0;

        foreach (Section::cases() as $section) {
            $sectionBase = $bases[$section->value] ?? 0.0;
            $percent = $percents[$section->value] ?? 0.0;

            // Sekcja bez pozycji i bez rabatu nie zasluguje na wiersz
            // w podsumowaniu — stary system pokazywal wszystkie cztery,
            // w tym trzy wypelnione zerami.
            if ($sectionBase === 0.0 && $percent === 0.0) {
                continue;
            }

            $sectionDiscount = round($sectionBase * $percent / 100, 2);

            $base += $sectionBase;
            $discount += $sectionDiscount;

            $sections[] = [
                'section' => $section->value,
                'base' => $this->money($sectionBase),
                'percent' => $this->percent($percent),
                'discount' => $this->money($sectionDiscount),
                'net' => $this->money($sectionBase - $sectionDiscount),
            ];
        }

        $net = round($base - $discount, 2);
        $vatRate = $order->invoiceType?->vat_rate;
        $vat = $vatRate === null ? null : round($net * $vatRate / 100, 2);

        return new OrderTotals(
            base: $this->money($base),
            discount: $this->money($discount),
            net: $this->money($net),
            excludedNet: $this->money($this->sum($order, false)),
            vatRate: $vatRate,
            vat: $vat === null ? null : $this->money($vat),
            gross: $vat === null ? null : $this->money($net + $vat),
            sections: $sections,
        );
    }

    /** Sama kwota netto po rabacie — dla list i warunków przejść. */
    public function net(Order $order): string
    {
        return $this->totals($order)->net;
    }

    /**
     * Podstawa rabatu w podziale na sekcje asortymentu, z list
     * wliczonych do zlecenia.
     *
     * @return array<string, float>
     */
    private function bases(Order $order): array
    {
        $bases = [];

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if (!$list->is_included) {
                continue;
            }

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $key = $item->section->value;
                $amount = (float) $item->amount;

                foreach ($item->processes as $process) {
                    $amount += (float) $process->amount;
                }

                $bases[$key] = ($bases[$key] ?? 0.0) + $amount;
            }
        }

        return $bases;
    }

    /**
     * @return array<string, float>
     */
    private function percents(Order $order): array
    {
        $percents = [];

        /** @var OrderDiscount $discount */
        foreach ($order->discounts as $discount) {
            $percents[$discount->section->value] = (float) $discount->percent;
        }

        return $percents;
    }

    private function sum(Order $order, bool $included): float
    {
        $total = 0.0;

        /** @var OrderList $list */
        foreach ($order->lists as $list) {
            if ((bool) $list->is_included !== $included) {
                continue;
            }

            /** @var OrderItem $item */
            foreach ($list->items as $item) {
                $total += (float) $item->amount;

                foreach ($item->processes as $process) {
                    $total += (float) $process->amount;
                }
            }
        }

        return $total;
    }

    private function money(float $value): string
    {
        return number_format(round($value, 2), 2, '.', '');
    }

    private function percent(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
