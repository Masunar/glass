<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Section;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Poziom 4 ustalania ceny: rabat na zleceniu, osobny dla każdej sekcji
 * asortymentu.
 *
 * @property int $order_id
 * @property Section $section
 * @property string $percent
 * @property int|null $approved_by
 */
class OrderDiscount extends Dateable
{
    protected $table = 'order_discounts';

    protected $fillable = ['order_id', 'section', 'percent', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return [
            'section' => Section::class,
            'percent' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}
