<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use Carbon\Carbon;
use App\Models\Product;
use App\Enum\PriceSource;
use App\Models\Contractor;
use App\Models\PriceSection;
use App\Models\PriceListItem;
use App\DTO\Pricing\QuoteStep;
use App\Models\ContractorPrice;
use App\DTO\Pricing\ResolvedPrice;
use App\Enum\PriceUnavailableReason;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trzy z czterech poziomów ustalania ceny.
 *
 * `catalogue()` daje poziom 1 — cenę katalogową produktu w danej sekcji
 * cenowej. `forContractor()` dokłada poziom 2 (sekcja cenowa
 * kontrahenta) i 3 (cena indywidualna). Poziom 4 — rabat na zleceniu —
 * należy do modułu zleceń, bo zależy od pozycji, nie od kontrahenta.
 *
 * Każdy poziom dopisuje krok do śladu, więc handlowiec widzi, skąd
 * wzięła się kwota, zamiast patrzeć na liczbę bez pochodzenia
 * (`99-model-danych.md` §6.3).
 *
 * Cena cennikowa jest zamrożona do momentu świadomego zapisu w module
 * cennika. To nie jest niedopatrzenie, tylko wymaganie: w starym
 * systemie każda dostawa po nowej cenie przeliczała cały cennik, więc
 * oferta wystawiona wczoraj przestawała być aktualna dzisiaj.
 */
final readonly class PriceResolver
{
    public function catalogue(
        Product $product,
        ?PriceSection $priceSection = null,
        ?Carbon $date = null,
    ): ResolvedPrice {
        $date ??= Carbon::today();
        $priceSection ??= $this->defaultSectionFor($product);

        if ($priceSection === null) {
            return ResolvedPrice::unavailable(PriceUnavailableReason::NO_PRICE_SECTION);
        }

        $item = $this->itemAt($product->id, $priceSection->id, $date);

        if ($item === null) {
            return ResolvedPrice::unavailable(
                PriceUnavailableReason::NO_PRICE_LIST_ITEM,
                $priceSection->id,
                $priceSection->name,
            );
        }

        $netPrice = $item->effectiveNetPrice();

        if ($netPrice === null) {
            return ResolvedPrice::unavailable(
                PriceUnavailableReason::NO_PRICE,
                $priceSection->id,
                $priceSection->name,
            );
        }

        $source = $item->manual_net_price !== null ? PriceSource::MANUAL : PriceSource::COMPUTED;
        $purchase = $product->purchasePriceAt($date)?->net_price;
        $coefficient = (string) $item->coefficient;

        return ResolvedPrice::of(
            netPrice: $netPrice,
            source: $source,
            coefficient: $coefficient,
            purchaseNetPrice: $purchase,
            recomputedNetPrice: $purchase === null
                ? null
                : PriceListItem::computePrice($purchase, $coefficient),
            priceSectionId: $priceSection->id,
            priceSectionName: $priceSection->name,
            steps: [
                new QuoteStep(
                    'catalogue',
                    'Cena katalogowa',
                    $netPrice,
                    $source === PriceSource::MANUAL
                        ? sprintf('cena ręczna, sekcja „%s”', $priceSection->name)
                        : sprintf(
                            '%s zł zakupu × %s, sekcja „%s”',
                            $purchase ?? '?',
                            $this->trimCoefficient($coefficient),
                            $priceSection->name,
                        ),
                ),
            ],
        );
    }


    /**
     * Pełna ścieżka ceny dla kontrahenta — poziomy 1–3.
     *
     * 1. cena katalogowa produktu
     * 2. sekcja cenowa kontrahenta (per sekcja asortymentu)
     * 3. cena indywidualna kontrahenta
     *
     * Poziom 4 — rabat na zleceniu — dokłada moduł zleceń, bo zależy
     * od pozycji, nie od kontrahenta. Każdy poziom dopisuje krok do
     * śladu, więc handlowiec widzi, skąd wzięła się kwota, zamiast
     * patrzeć na liczbę bez pochodzenia.
     */
    public function forContractor(
        Product $product,
        ?Contractor $contractor = null,
        ?Carbon $date = null,
    ): ResolvedPrice {
        $date ??= Carbon::today();

        // Brak przypisanej sekcji nie blokuje wyceny: obowiązuje wtedy
        // domyślna (K-02). Blokada oznaczałaby, że nowego klienta nie da
        // się wycenić, dopóki ktoś nie uzupełni pięciu wierszy.
        $section = $contractor?->priceSectionFor($product->section);
        $price = $this->catalogue($product, $section, $date);

        $individual = $contractor === null
            ? null
            : $this->individualPriceAt($contractor->id, $product->id, $date);

        if ($individual === null) {
            return $price;
        }

        $net = (string) $individual->net_price;

        return ResolvedPrice::of(
            netPrice: $net,
            source: PriceSource::INDIVIDUAL,
            coefficient: $price->coefficient,
            purchaseNetPrice: $price->purchaseNetPrice,
            recomputedNetPrice: $price->recomputedNetPrice,
            priceSectionId: $price->priceSectionId,
            priceSectionName: $price->priceSectionName,
            steps: [
                ...$price->steps,
                new QuoteStep(
                    'individual',
                    'Cena indywidualna',
                    $net,
                    sprintf('ustalona dla %s', $contractor->displayName()),
                ),
            ],
        );
    }

    private function individualPriceAt(int $contractorId, int $productId, Carbon $date): ?ContractorPrice
    {
        /** @var ContractorPrice|null */
        return ContractorPrice::query()
            ->where('contractor_id', $contractorId)
            ->where('product_id', $productId)
            ->whereDate('valid_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Sekcja cenowa używana, gdy kontrahent nie ma przypisanej własnej.
     * Domyślna jest dokładnie jedna na sekcję asortymentu.
     */
    public function defaultSectionFor(Product $product): ?PriceSection
    {
        /** @var PriceSection|null */
        return PriceSection::query()
            ->where('section', $product->section->value)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    private function itemAt(int $productId, int $priceSectionId, Carbon $date): ?PriceListItem
    {
        /** @var PriceListItem|null */
        return PriceListItem::query()
            ->where('product_id', $productId)
            ->where('price_section_id', $priceSectionId)
            ->whereDate('valid_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    private function trimCoefficient(string $coefficient): string
    {
        return rtrim(rtrim($coefficient, '0'), '.') ?: '0';
    }
}
