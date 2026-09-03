<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Section;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja listy.
 *
 * Cena i koszt są snapshotem, nie referencją do cennika: oferta
 * wystawiona wczoraj musi pokazywać tę samą kwotę po dzisiejszej
 * dostawie, która zmieniła cenę zakupu.
 *
 * `price_path` przechowuje ślad wyliczenia z chwili wyceny — bez niego
 * przy czterech nakładających się poziomach cen nie da się odpowiedzieć,
 * skąd wzięła się kwota.
 *
 * @property int $order_list_id
 * @property int|null $product_id
 * @property Section $section
 * @property string $name
 * @property string $quantity
 * @property string $unit_net_price
 * @property string|null $unit_cost
 * @property string $amount
 * @property array<int, mixed>|null $price_path
 * @property-read OrderPane|null $pane
 * @property-read Collection<int, OrderItemProcess> $processes
 */
class OrderItem extends Dateable
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_list_id', 'product_id', 'section', 'name', 'quantity',
        'unit_net_price', 'unit_cost', 'amount', 'price_path', 'position',
    ];

    protected function casts(): array
    {
        return [
            'section' => Section::class,
            'quantity' => 'decimal:3',
            'unit_net_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'amount' => 'decimal:2',
            'price_path' => 'array',
            'position' => 'integer',
        ];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(OrderList::class, 'order_list_id', 'id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function pane(): HasOne
    {
        return $this->hasOne(OrderPane::class, 'order_item_id', 'id');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(OrderItemProcess::class, 'order_item_id', 'id');
    }
}
