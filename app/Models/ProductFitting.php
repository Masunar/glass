<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $product_id
 * @property string|null $finish
 * @property string|null $dimension
 */
class ProductFitting extends Model
{
    public $timestamps = false;

    protected $table = 'product_fittings';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = ['product_id', 'finish', 'dimension'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
