<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Nazwany zestaw uprawnień nadawany roli.
 *
 * **Wiązanie, nie szablon** (U-07). Rola ma paczkę, nie jej kopię, więc
 * poprawka w paczce „Handlowiec" natychmiast działa we wszystkich
 * rolach, które ją mają. To jest wygodne i to jest niebezpieczne z tego
 * samego powodu — dlatego `roles()` istnieje i ekran edycji paczki
 * **musi** z niego skorzystać, żeby pokazać zasięg zmiany, zanim ktoś
 * zapisze.
 *
 * @property string $name
 * @property string|null $description
 * @property int|null $permissions_count
 * @property int|null $roles_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, Role> $roles
 */
class PermissionPackage extends Dateable
{
    protected $table = 'permission_packages';

    protected $fillable = ['name', 'description'];

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'permission_package_items',
            'permission_package_id',
            'permission_id',
        );
    }

    /**
     * Role, które tę paczkę mają — czyli **kogo dotknie** jej zmiana.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission_packages',
            'permission_package_id',
            'role_id',
        );
    }
}
