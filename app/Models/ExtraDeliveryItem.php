<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $extra_delivery_id
 * @property int $product_id
 * @property string $quantity
 * @property-read Product|null $product
 */
class ExtraDeliveryItem extends Dateable
{
    protected $table = 'extra_delivery_items';

    protected $fillable = ['extra_delivery_id', 'product_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
