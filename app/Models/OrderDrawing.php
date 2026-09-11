<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rysunek do zlecenia.
 *
 * Plik leży na dysku pod wygenerowaną nazwą, a w bazie zostaje nazwa
 * nadana przez człowieka. Dwa pliki „rysunek.pdf" z dwóch zleceń nie
 * mogą się nadpisać, a lista ma pokazywać to, co użytkownik wgrał.
 *
 * @property int $order_id
 * @property int|null $order_item_id
 * @property string $original_name
 * @property string $stored_path
 * @property string $mime
 * @property int $size_bytes
 * @property string|null $note
 * @property int|null $uploaded_by
 * @property-read Order|null $order
 * @property-read OrderItem|null $item
 * @property-read User|null $uploader
 */
class OrderDrawing extends Dateable
{
    protected $table = 'order_drawings';

    protected $fillable = [
        'order_id', 'order_item_id', 'original_name', 'stored_path',
        'mime', 'size_bytes', 'note', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** Czy da się pokazać podgląd, czy tylko pobrać. */
    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id');
    }
}
