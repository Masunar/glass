<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\ProductionIssue;
use App\Enum\ProductionStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etap produkcyjny na pozycji zlecenia.
 *
 * Wykonanie jest osobne od marszruty wycenowej. `order_item_processes`
 * są kasowane i zakładane od nowa przy każdym zapisie formatki, bo cena
 * jest snapshotem — gdyby stan wykonania siedział tam, poprawka wymiaru
 * kasowałaby historię pracy hali.
 *
 * @property int $order_id
 * @property int $order_item_id
 * @property int $process_id
 * @property int|null $workstation_id
 * @property string|null $parameter
 * @property int $position
 * @property ProductionStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $minutes_spent
 * @property ProductionIssue|null $issue_type
 * @property string|null $note
 * @property int|null $done_by
 * @property-read Order $order
 * @property-read OrderItem $item
 * @property-read Process $process
 * @property-read Workstation|null $workstation
 * @property-read User|null $doneBy
 */
class ProductionTask extends Dateable
{
    protected $table = 'production_tasks';

    protected $fillable = [
        'order_id', 'order_item_id', 'process_id', 'workstation_id',
        'parameter', 'position', 'status', 'started_at', 'finished_at',
        'minutes_spent', 'issue_type', 'note', 'done_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductionStatus::class,
            'issue_type' => ProductionIssue::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'position' => 'integer',
            'minutes_spent' => 'integer',
        ];
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

    /** @return BelongsTo<Process, $this> */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id', 'id');
    }

    /** @return BelongsTo<Workstation, $this> */
    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class, 'workstation_id', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by', 'id');
    }
}
