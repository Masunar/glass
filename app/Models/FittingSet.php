<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Komplet okuć. Dwa różne byty pod wspólną tabelą, rozdzielone polem `kind`:
 *
 * - TEMPLATE — kuratorowana biblioteka szablonów wielokrotnego użytku,
 * - CONFIGURATION — konfiguracja zapisana pod konkretnego kontrahenta.
 *
 * W starym systemie oba dzieliły jedną listę, więc obok sensownych
 * szablonów leżały pozycje nazwane nazwiskiem klienta, „140”, „test”.
 * To nie było niechlujstwo, tylko brakująca funkcja: handlowiec
 * potrzebował zapisać komplet dla klienta i nie miał gdzie.
 *
 * @property string $name
 * @property string $kind
 * @property int|null $customer_id
 */
class FittingSet extends Dateable
{
    public const KIND_TEMPLATE = 'template';
    public const KIND_CONFIGURATION = 'configuration';

    protected $table = 'fitting_sets';

    protected $fillable = ['name', 'kind', 'customer_id', 'is_active', 'created_by'];

    public function items(): HasMany
    {
        return $this->hasMany(FittingSetItem::class, 'fitting_set_id', 'id');
    }
}
