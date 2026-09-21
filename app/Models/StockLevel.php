<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stan produktu w lokalizacji — projekcja rejestru ruchów.
 *
 * `quantity` i `reserved` **nie są polami do wpisania**. Zmienia je
 * wyłącznie `StockLedger`, przy okazji zapisu ruchu i w tej samej
 * transakcji. Progi `min`/`max` są czym innym: to decyzja zakupowa
 * człowieka, więc je się edytuje wprost.
 *
 * @property int $product_id
 * @property int|null $location_id
 * @property string $quantity stan fizyczny
 * @property string $reserved ilość obiecana zleceniom
 * @property string $min_quantity
 * @property string $max_quantity
 * @property-read Product $product
 * @property-read Location|null $location
 */
class StockLevel extends Dateable
{
    protected $table = 'stock_levels';

    protected $fillable = [
        'product_id', 'location_id', 'quantity', 'reserved',
        'min_quantity', 'max_quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved' => 'decimal:3',
            'min_quantity' => 'decimal:3',
            'max_quantity' => 'decimal:3',
        ];
    }

    /**
     * Ile można jeszcze obiecać. Bez tej różnicy dwa zlecenia „widzą"
     * te same trzy sztuki.
     */
    public function available(): float
    {
        return (float) $this->quantity - (float) $this->reserved;
    }

    /**
     * Sugestia zakupowa: `max(0, Max − Stan)` gdy `Stan < Min`.
     *
     * Wzór odtworzony i potwierdzony na siedmiu pozycjach starego
     * systemu (`40-magazyn.md` §3.2). Liczy się od stanu **fizycznego**,
     * nie dostępnego — rezerwacja mówi, komu towar obiecano, a nie że
     * go nie ma na półce.
     */
    public function toOrder(): float
    {
        if ((float) $this->quantity >= (float) $this->min_quantity) {
            return 0.0;
        }

        return max(0.0, (float) $this->max_quantity - (float) $this->quantity);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
