<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\StatusDomain;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pozycja słownika statusów.
 *
 * Jedna tabela obsługuje wszystkie dziedziny (zlecenie, pomiar,
 * reklamacja, hartowanie, prowizja). Dzięki temu dodanie statusu jest
 * wstawieniem wiersza, a nie zmianą kodu — w starym systemie statusy
 * zlecenia były zaszyte w nazwach widoków SQL i katalog rozjeżdżał się
 * między modułami.
 *
 * @property StatusDomain $domain
 * @property string $code
 * @property string $name
 * @property string|null $short_name
 * @property int $position
 * @property string|null $color
 * @property bool $is_default
 * @property bool $is_final
 * @property bool $is_active
 * @property int|null $legacy_id
 */
class Status extends Dateable
{
    protected $table = 'statuses';

    protected $fillable = [
        'domain',
        'code',
        'name',
        'short_name',
        'position',
        'color',
        'is_default',
        'is_final',
        'is_active',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'domain' => StatusDomain::class,
            'is_final' => 'boolean',
            'position' => 'integer',
            'legacy_id' => 'integer',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeDomain(Builder $query, StatusDomain $domain): Builder
    {
        return $query->where('domain', $domain->value);
    }

    /** @return HasMany<StatusTransition, $this> */
    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'from_status_id', 'id');
    }

    /** @return HasMany<StatusTransition, $this> */
    public function transitionsTo(): HasMany
    {
        return $this->hasMany(StatusTransition::class, 'to_status_id', 'id');
    }

    public static function findByCode(StatusDomain $domain, string $code): ?self
    {
        /** @var self|null */
        return self::query()
            ->where('domain', $domain->value)
            ->where('code', $code)
            ->first();
    }
}
