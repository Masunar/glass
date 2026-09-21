<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\TemperingBatchStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Partia wysyłkowa do hartowni.
 *
 * @property int $number
 * @property int $supplier_id
 * @property TemperingBatchStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $expected_at
 * @property Carbon|null $returned_at
 * @property string|null $net_cost
 * @property string|null $document
 * @property string|null $note
 * @property-read Supplier|null $supplier
 * @property-read Collection<int, TemperingItem> $items
 */
class TemperingBatch extends Dateable
{
    protected $table = 'tempering_batches';

    protected $fillable = [
        'number', 'supplier_id', 'status', 'sent_at', 'expected_at',
        'returned_at', 'net_cost', 'document', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => TemperingBatchStatus::class,
            'sent_at' => 'date',
            'expected_at' => 'date',
            'returned_at' => 'date',
            'net_cost' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'id');
    }

    /** @return HasMany<TemperingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TemperingItem::class, 'tempering_batch_id', 'id');
    }

    /**
     * Ile dni partia jest poza zakładem.
     *
     * Liczone od wysyłki do powrotu albo do dziś. To jedyna liczba,
     * która mówi, czy podwykonawca się spóźnia — w starym systemie
     * nie było ani jednej daty (`20-hartownia.md` §4).
     */
    public function daysOut(): ?int
    {
        if ($this->sent_at === null) {
            return null;
        }

        return (int) $this->sent_at->diffInDays($this->returned_at ?? Carbon::today());
    }
}
