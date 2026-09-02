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
 * @property Carbon|null $resolved_at
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
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id', 'id');
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'alertable_type', 'alertable_id');
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }
}
