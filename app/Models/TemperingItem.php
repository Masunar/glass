<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use App\Enum\TemperingItemStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedna formatka (albo jej część) w drodze do pieca.
 *
 * Pozycja niesie własną ilość, bo z dwudziestu czterech sztuk potłuc
 * się mogą trzy — i wtedy wraca dwadzieścia jeden, a nie nic.
 *
 * @property int $order_item_id
 * @property int|null $tempering_batch_id
 * @property TemperingItemStatus $status
 * @property string $quantity
 * @property int|null $replaces_id
 * @property string|null $note
 * @property-read OrderItem|null $item
 * @property-read TemperingBatch|null $batch
 */
class TemperingItem extends Dateable
{
    protected $table = 'tempering_items';

    protected $fillable = [
        'order_item_id', 'tempering_batch_id', 'status',
        'quantity', 'replaces_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => TemperingItemStatus::class,
            'quantity' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }

    /** @return BelongsTo<TemperingBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(TemperingBatch::class, 'tempering_batch_id', 'id');
    }

    /** @return BelongsTo<TemperingItem, $this> */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(TemperingItem::class, 'replaces_id', 'id');
    }
}
