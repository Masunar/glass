<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja cennika zakupowego procesu. Koszt zależy od grubości
 * obrabianego szkła — stąd `glass_thickness_mm` obok `process_id`.
 *
 * @property int $product_id
 * @property int|null $process_id
 * @property float|null $glass_thickness_mm
 * @property string|null $variant
 */
class ProductService extends Model
{
    public $timestamps = false;

    protected $table = 'product_services';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = ['product_id', 'process_id', 'glass_thickness_mm', 'variant'];

    protected function casts(): array
    {
        return ['glass_thickness_mm' => 'float'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id', 'id');
    }
}
