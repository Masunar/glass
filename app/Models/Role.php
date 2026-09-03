<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rola użytkownika.
 *
 * Rozszerza model Spatie o `is_superuser` — kolumnę dodaną migracją do
 * ich tabeli. Sam pakiet o niej nie wie, więc bez tej klasy każde
 * odczytanie flagi było obejściem: raz `$role['is_superuser']`, raz
 * `getAttribute('is_superuser')`, a analiza statyczna nie miała szans
 * niczego sprawdzić.
 *
 * Rola nadrzędna omija sprawdzanie uprawnień w całości (`Gate::before`
 * w AppServiceProvider), więc to najbardziej doniosłe pole w systemie
 * uprawnień i nie powinno mieszkać poza modelem.
 *
 * Podstawiona w `config/permission.php` → `models.role`, dzięki czemu
 * `$user->roles` oddaje tę klasę, a nie klasę pakietu.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_superuser
 */
class Role extends SpatieRole
{
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_superuser' => 'boolean',
        ];
    }

    /**
     * Limity rabatowe tej roli w poszczególnych sekcjach cenowych.
     *
     * Wiersz rola × sekcja, nie kolumna — stary system miał kolumny dla
     * trzech ról, więc czwarta rola sprzedażowa wymagałaby zmiany tabeli.
     *
     * @return HasMany<RoleDiscountLimit, $this>
     */
    public function discountLimits(): HasMany
    {
        return $this->hasMany(RoleDiscountLimit::class, 'role_id', 'id');
    }
}
