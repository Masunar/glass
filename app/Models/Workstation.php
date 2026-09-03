<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property int|null $location_id
 * @property int|null $daily_capacity
 * @property int $position
 * @property bool $is_active
 */
class Workstation extends Dateable
{
    protected $table = 'workstations';

    protected $fillable = ['name', 'location_id', 'daily_capacity', 'is_active', 'position'];

    protected function casts(): array
    {
        return ['daily_capacity' => 'integer', 'position' => 'integer'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class, 'workstation_id', 'id');
    }
}
