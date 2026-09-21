<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use Carbon\Carbon;
use App\Models\Product;
use App\Models\PurchasePrice;
use App\Enum\PurchasePriceSource;
use Illuminate\Support\Facades\Auth;

/**
 * Jedyne miejsce, w którym zmienia się cena zakupu.
 *
 * Zmiana zamyka poprzedni okres datą i zakłada nowy. Bez tego nie da
 * się odpowiedzieć, dlaczego zlecenie sprzed pół roku miało taką marżę.
 *
 * Wydzielone z `ProductCatalogService`, bo od przyjęcia towaru cena
 * zakupu zmienia się z dwóch stron — z ręki i z dostawy. Dwie kopie
 * tej samej logiki okresów rozjechałyby się przy pierwszej poprawce.
 */
final readonly class PurchasePriceLedger
{
    /**
     * Ustawia cenę obowiązującą od `$on`.
     *
     * `null` znaczy „od tego dnia ceny nie znamy" i zamyka bieżący
     * okres bez otwierania nowego — brak ceny to brak, nie zero.
     * Cena identyczna z obowiązującą nie zakłada nowego okresu: nic się
     * nie zmieniło, więc nie ma czego dokumentować.
     */
    public function set(
        Product $product,
        ?string $netPrice,
        Carbon $on,
        PurchasePriceSource $source,
    ): ?PurchasePrice {
        $current = $product->purchasePriceAt($on);

        if ($netPrice === null) {
            $current?->update(['valid_to' => $on->copy()->subDay()]);

            return null;
        }

        $normalized = number_format((float) $netPrice, 2, '.', '');

        if ($current !== null && $current->net_price === $normalized) {
            return $current;
        }

        // Druga dostawa tego samego dnia nadpisuje okres zamiast zakladac
        // drugi o tej samej dacie poczatku — inaczej `purchasePriceAt()`
        // musialoby rozstrzygac, ktory z dwoch jest wazniejszy.
        if ($current !== null && $current->valid_from->isSameDay($on)) {
            $current->update([
                'net_price' => $normalized,
                'source' => $source->value,
                'created_by' => Auth::id(),
            ]);

            return $current->refresh();
        }

        $current?->update(['valid_to' => $on->copy()->subDay()]);

        /** @var PurchasePrice */
        return PurchasePrice::query()->create([
            'product_id' => $product->id,
            'net_price' => $normalized,
            'source' => $source->value,
            'valid_from' => $on,
            'created_by' => Auth::id(),
        ]);
    }
}
