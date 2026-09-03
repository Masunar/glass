<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proces technologiczny na pozycji — cięcie, szlif, wiercenie.
 *
 * @property int $order_item_id
 * @property int $process_id
 * @property string|null $parameter
 * @property string $unit_net_price
 * @property string $amount
 * @property-read Process|null $process
 */
class OrderItemProcess extends Dateable
{
    protected $table = 'order_item_processes';

    protected $fillable = [
        'order_item_id', 'process_id', 'parameter',
        'unit_net_price', 'unit_cost', 'amount', 'position',
    ];

    protected function casts(): array
    {
        return [
            'unit_net_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id', 'id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }
}
