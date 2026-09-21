<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja przyjęcia: ile sztuk i po jakiej cenie faktycznie przyszło.
 *
 * @property int $purchase_receipt_id
 * @property int $purchase_order_item_id
 * @property string $quantity
 * @property string|null $unit_net_price
 * @property-read PurchaseOrderItem|null $orderItem
 */
class PurchaseReceiptItem extends Dateable
{
    protected $table = 'purchase_receipt_items';

    protected $fillable = ['purchase_receipt_id', 'purchase_order_item_id', 'quantity', 'unit_net_price'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_net_price' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id', 'id');
    }

    /** @return BelongsTo<PurchaseReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id', 'id');
    }
}
