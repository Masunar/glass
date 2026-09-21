<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use App\Enum\StockMovementType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeden ruch magazynowy — wpis w rejestrze, którego się nie poprawia.
 *
 * Stan magazynowy jest sumą tych wierszy. Pomyłkę prostuje się kolejnym
 * ruchem (korektą), nie edycją poprzedniego: inaczej wracamy do stanu
 * jako pola, którego historii nie da się odtworzyć.
 *
 * @property int $product_id
 * @property int|null $location_id
 * @property StockMovementType $type
 * @property string $quantity
 * @property int|null $order_id
 * @property string|null $document
 * @property string|null $note
 * @property int|null $created_by
 * @property-read Product $product
 * @property-read Location|null $location
 * @property-read Order|null $order
 */
class StockMovement extends Dateable
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id', 'location_id', 'type', 'quantity',
        'order_id', 'document', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
        ];
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
