<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Strona aplikacji i uprawnienie, które ją otwiera.
 *
 * Wiersz istnieje dla każdej trasy z rejestru; zmienialne jest
 * **przypisanie**, nie istnienie strony. Dzięki temu nowa trasa
 * pojawia się na ekranie konfiguracji od razu — jako niepokryta,
 * a nie jako coś, o czym nikt nie wie.
 *
 * @property string $code
 * @property string $path
 * @property string|null $module
 * @property string $label
 * @property int|null $permission_id
 * @property int $position
 * @property-read Permission|null $permission
 */
class AppPage extends Dateable
{
    protected $table = 'app_pages';

    protected $fillable = ['code', 'path', 'module', 'label', 'permission_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id', 'id');
    }
}
