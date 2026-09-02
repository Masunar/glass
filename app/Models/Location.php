<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Punkt firmy — Stobno i Chopina.
 *
 * Lokalizacja jest atrybutem, nie granicą izolacji danych: zlecenia,
 * cenniki i magazyn są wspólne, a rozdzielenie widoczności realizują
 * uprawnienia o zakresie LOCATION.
 *
 * @property string $name
 * @property string|null $short_name
 * @property bool $is_production
 * @property bool $is_pickup_point
 * @property bool $is_default
 * @property bool $is_active
 * @property int|null $legacy_id
 */
class Location extends Dateable
{
    protected $table = 'locations';

    protected $fillable = [
        'name',
        'short_name',
        'color',
        'address_street',
        'address_house',
        'address_flat',
        'address_postal_code',
        'address_city',
        'phone',
        'email',
        'is_production',
        'is_pickup_point',
        'is_default',
        'is_active',
        'position',
        'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'is_production' => 'boolean',
            'is_pickup_point' => 'boolean',
            'position' => 'integer',
            'legacy_id' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'location_id', 'id');
    }
}
