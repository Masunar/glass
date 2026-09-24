<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use App\Services\Orders\OrderValueStore;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proces technologiczny na pozycji — cięcie, szlif, wiercenie.
 *
 * `product_id` to **wybrana** pozycja cennikowa procesu, nie wyliczona.
 * Grubość szkła zawęża listę, ale jej nie rozstrzyga: fazowanie ma dla
 * jednej grubości osiem wierszy (faza 5…40 mm), a CNC cztery w ogóle od
 * grubości niezależne. Wybór należy do człowieka.
 *
 * @property int $order_item_id
 * @property int $process_id
 * @property int|null $product_id
 * @property string|null $parameter
 * @property int|null $days
 * @property string|null $comment
 * @property string $unit_net_price
 * @property string $amount
 * @property-read Process|null $process
 * @property-read Product|null $product
 */
class OrderItemProcess extends Dateable
{

    /**
     * Proces jest częścią kwoty pozycji, więc i wartości zlecenia.
     * Patrz `OrderValueStore`.
     */
    protected static function booted(): void
    {
        $stale = static function (self $process): void {
            OrderValueStore::markStaleByItem((int) $process->order_item_id);
        };

        static::saved($stale);
        static::deleted($stale);
    }

    protected $table = 'order_item_processes';

    protected $fillable = [
        'order_item_id', 'process_id', 'product_id', 'parameter',
        'days', 'comment', 'unit_net_price', 'unit_cost', 'amount', 'position',
    ];

    protected function casts(): array
    {
        return [
            'unit_net_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'position' => 'integer',
            'days' => 'integer',
        ];
    }

    /** @return BelongsTo<Process, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id', 'id');
    }

    /** Wybrana pozycja cennikowa — null, dopóki nikt nie wybrał. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }
}
