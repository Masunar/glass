<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jedno przyjęcie towaru z zamówienia.
 *
 * Dostawa częściowa to osobny dokument, a nie podniesiona liczba
 * w pozycji zamówienia — inaczej nie da się powiedzieć, która sztuka
 * przyszła kiedy i po jakiej cenie.
 *
 * @property int $purchase_order_id
 * @property Carbon $received_at
 * @property string|null $document
 * @property string|null $note
 * @property-read Collection<int, PurchaseReceiptItem> $items
 */
class PurchaseReceipt extends Dateable
{
    protected $table = 'purchase_receipts';

    protected $fillable = ['purchase_order_id', 'received_at', 'document', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['received_at' => 'date'];
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id');
    }

    /** @return HasMany<PurchaseReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class, 'purchase_receipt_id', 'id');
    }
}
