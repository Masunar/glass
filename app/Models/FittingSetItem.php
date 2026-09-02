<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $fitting_set_id
 * @property int $product_id
 * @property int $quantity
 */
class FittingSetItem extends Dateable
{
    protected $table = 'fitting_set_items';

    protected $fillable = ['fitting_set_id', 'product_id', 'quantity', 'position'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'position' => 'integer'];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(FittingSet::class, 'fitting_set_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
