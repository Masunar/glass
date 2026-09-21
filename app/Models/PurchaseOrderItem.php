<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja zamówienia do dostawcy.
 *
 * `quantity_received` jest sumą przyjęć, a nie polem, które ktoś
 * nadpisuje — utrzymuje ją `PurchaseOrderService::receive()`.
 *
 * @property int $purchase_order_id
 * @property int $product_id
 * @property string $quantity_ordered
 * @property string $quantity_received
 * @property string|null $unit_net_price
 * @property-read Product|null $product
 */
class PurchaseOrderItem extends Dateable
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id', 'product_id', 'quantity_ordered',
        'quantity_received', 'unit_net_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_net_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /** Ile jeszcze ma przyjechać. Nigdy ujemne — nadwyżkę widać osobno. */
    public function outstanding(): float
    {
        return max(0.0, round((float) $this->quantity_ordered - (float) $this->quantity_received, 3));
    }
}
