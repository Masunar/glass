<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Wystąpienie alertu na konkretnym rekordzie.
 *
 * @property int $alert_rule_id
 * @property string $alertable_type
 * @property int $alertable_id
 * @property string|null $value
 * @property-read Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property string|null $acknowledged_value
 * @property Carbon|null $resolved_at
 * @property-read AlertRule $rule
 * @property-read User|null $acknowledger
 */
class AlertOccurrence extends Model
{
    public $timestamps = false;

    protected $table = 'alert_occurrences';

    protected $fillable = [
        'alert_rule_id',
        'alertable_type',
        'alertable_id',
        'value',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledged_value',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id', 'id');
    }

    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by', 'id');
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'alertable_type', 'alertable_id');
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): Builder
    {
        $query->whereNull('resolved_at');

        return $query;
    }
}
