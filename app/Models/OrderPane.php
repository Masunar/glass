<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property bool $is_irregular_shape
 * @property bool $is_tempered
 * @property bool $needs_mark
 */
class OrderPane extends Model
{
    public $timestamps = false;

    protected $table = 'order_panes';

    protected $primaryKey = 'order_item_id';

    public $incrementing = false;

    protected $fillable = [
        'order_item_id', 'width_mm', 'height_mm',
        'is_irregular_shape', 'is_tempered', 'needs_mark', 'pane_template_id',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'is_irregular_shape' => 'boolean',
            'is_tempered' => 'boolean',
            'needs_mark' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }
}
