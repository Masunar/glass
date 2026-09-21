<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zamówienie do dostawcy.
 *
 * @property int $number
 * @property int $supplier_id
 * @property PurchaseOrderStatus $status
 * @property Carbon|null $ordered_at
 * @property Carbon|null $expected_at
 * @property string|null $note
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read Collection<int, PurchaseReceipt> $receipts
 */
class PurchaseOrder extends Dateable
{
    protected $table = 'purchase_orders';

    protected $fillable = [
        'number', 'supplier_id', 'status', 'ordered_at', 'expected_at', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'date',
            'expected_at' => 'date',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id', 'id');
    }

    /** @return HasMany<PurchaseReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class, 'purchase_order_id', 'id');
    }
}
