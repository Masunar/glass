<?php

declare(strict_types=1);

namespace Salvon\Model;

use Override;
use Salvon\Model\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Model preset for handling log data related entities
 *
 * @property int $model_id
 * @property int $executed_by
 * @property array $changes
 * @property array $snapshot_before
 * @property array $snapshot_after
 * @property string $action
 * @property Authenticatable $executedBy
 *
 */
abstract class Log extends Dateable
{
    protected $fillable = [
        'model_id',
        'executed_by',
        'action',
        'changes',
        'snapshot_before',
        'snapshot_after',
    ];

    public function executedBy(): HasOne
    {
        return $this->hasOne(Authenticatable::class, 'id', 'executed_by');
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'snapshot_after' => 'array',
            'snapshot_before' => 'array',
        ];
    }
}
