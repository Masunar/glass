<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Unit;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proces technologiczny — pozycja słownika, nie kolumna w kodzie.
 *
 * W starym systemie trzynaście procesów było kolumnami siatki produkcji,
 * więc dodanie procesu wymagało zmiany layoutu. Tutaj siatka generuje
 * kolumny z tego słownika.
 *
 * @property string $code
 * @property string $name
 * @property int|null $workstation_id
 * @property Unit $unit
 * @property int|null $duration_days
 * @property bool $is_subcontracted
 * @property bool $requires_parameter
 * @property int $default_order
 */
class Process extends Dateable
{
    protected $table = 'processes';

    protected $fillable = [
        'code', 'name', 'workstation_id', 'unit', 'duration_days',
        'setup_minutes', 'unit_minutes', 'buffer_days',
        'is_subcontracted', 'requires_parameter', 'default_order',
        'is_active', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'duration_days' => 'integer',
            'setup_minutes' => 'integer',
            'unit_minutes' => 'integer',
            'buffer_days' => 'integer',
            'is_subcontracted' => 'boolean',
            'requires_parameter' => 'boolean',
            'default_order' => 'integer',
            'legacy_id' => 'integer',
        ];
    }

    public function workstation(): BelongsTo
    {
        return $this->belongsTo(Workstation::class, 'workstation_id', 'id');
    }

    public static function findByCode(string $code): ?self
    {
        /** @var self|null */
        return self::query()->where('code', $code)->first();
    }
}
