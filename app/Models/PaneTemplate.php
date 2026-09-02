<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Szablon powtarzalnej formatki.
 *
 * Mechanizm wart zachowania: powtarzalne zamówienie wprowadza się jednym
 * kliknięciem zamiast przepisywać wymiary. Nazwa jest wymagana i unikalna,
 * bo w starym systemie biblioteka zaśmieciła się czterema pozycjami
 * o nazwie „nowa formatka”. `last_used_at` pozwala archiwizować nieużywane.
 *
 * @property string $name
 * @property int $product_id
 * @property int $width_mm
 * @property int $height_mm
 * @property Carbon|null $last_used_at
 */
class PaneTemplate extends Dateable
{
    protected $table = 'pane_templates';

    protected $fillable = [
        'name', 'product_id', 'width_mm', 'height_mm',
        'min_billable_m2', 'is_active', 'last_used_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'width_mm' => 'integer',
            'height_mm' => 'integer',
            'min_billable_m2' => 'decimal:2',
            'last_used_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
