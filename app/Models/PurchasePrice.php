<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\PurchasePriceSource;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cena zakupu w danym okresie.
 *
 * Trzymana osobno od cennika sprzedaży, bo w starym systemie każda
 * dostawa po nowej cenie automatycznie przeliczała cały cennik —
 * oferta wystawiona wczoraj przestawała być aktualna dzisiaj.
 *
 * @property int $product_id
 * @property string $net_price
 * @property PurchasePriceSource $source
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class PurchasePrice extends Dateable
{
    protected $table = 'purchase_prices';

    protected $fillable = ['product_id', 'net_price', 'source', 'valid_from', 'valid_to', 'created_by'];

    protected function casts(): array
    {
        return [
            'source' => PurchasePriceSource::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
            'net_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
