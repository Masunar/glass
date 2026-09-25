<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\ExtraDeliveryReason;
use App\Enum\ExtraDeliveryStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dostawa dodatkowa do konkretnego zlecenia — reklamacja, błędne
 * okucie, domówienie. Patrz `ExtraDeliveryService`.
 *
 * @property int $number
 * @property int $order_id
 * @property int|null $supplier_id
 * @property ExtraDeliveryReason $reason
 * @property ExtraDeliveryStatus $status
 * @property Carbon|null $expected_at
 * @property Carbon|null $received_at
 * @property int|null $received_by
 * @property string|null $note
 * @property-read Order|null $order
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, ExtraDeliveryItem> $items
 */
class ExtraDelivery extends Dateable
{
    protected $table = 'extra_deliveries';

    protected $fillable = [
        'number', 'order_id', 'supplier_id', 'reason', 'status',
        'expected_at', 'received_at', 'received_by', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'reason' => ExtraDeliveryReason::class,
            'status' => ExtraDeliveryStatus::class,
            'expected_at' => 'date',
            'received_at' => 'date',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /** @return HasMany<ExtraDeliveryItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ExtraDeliveryItem::class, 'extra_delivery_id', 'id');
    }
}
