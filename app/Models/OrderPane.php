<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\PaneShape;
use Salvon\Model\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Formatka — wymiary pozycji szklanej.
 *
 * Powierzchnia, obwód i waga nie są kolumnami: to samo wyliczenie
 * w starym systemie dawało trzy różne wyniki w trzech modułach.
 *
 * @property int $order_item_id
 * @property int $width_mm
 * @property int $height_mm
 * @property PaneShape $shape
 * @property bool $is_tempered
 * @property bool $needs_mark
 * @property float|null $min_billable_m2
 */
class OrderPane extends Model
{
    public $timestamps = false;

    protected $table = 'order_panes';

    protected $primaryKey = 'order_item_id';

    public $incrementing = false;

    protected $fillable = [
        'order_item_id', 'width_mm', 'height_mm',
        'shape', 'is_tempered', 'needs_mark', 'pane_template_id',
        'min_billable_m2',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'shape' => PaneShape::class,
            'is_tempered' => 'boolean',
            'needs_mark' => 'boolean',
            'min_billable_m2' => 'float',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }
}
