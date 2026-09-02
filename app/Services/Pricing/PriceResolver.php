<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use Carbon\Carbon;
use App\Models\Product;
use App\Enum\PriceSource;
use App\Models\PriceSection;
use App\Models\PriceListItem;
use App\DTO\Pricing\QuoteStep;
use App\DTO\Pricing\ResolvedPrice;
use App\Enum\PriceUnavailableReason;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pierwszy z czterech poziomów ustalania ceny: cena katalogowa.
 *
 * Kolejne trzy — sekcja cenowa kontrahenta, cena indywidualna i rabat
 * na zleceniu — dokładają się do tego wyniku, gdy powstaną moduły
 * kontrahentów i zleceń. Każdy poziom dopisuje krok do śladu, więc
 * handlowiec widzi, skąd wzięła się kwota, zamiast patrzeć na liczbę
 * bez pochodzenia (`99-model-danych.md` §6.3).
 *
 * Cena cennikowa jest zamrożona do momentu świadomego zapisu w module
 * cennika. To nie jest niedopatrzenie, tylko wymaganie: w starym
 * systemie każda dostawa po nowej cenie przeliczała cały cennik, więc
 * oferta wystawiona wczoraj przestawała być aktualna dzisiaj.
 */
final readonly class PriceResolver
{
    public function resolve(
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
