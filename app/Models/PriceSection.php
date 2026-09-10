<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Section;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Poziom cenowy w obrębie sekcji asortymentu.
 *
 * Logika wart zachowania: im tańsza sekcja cenowa, tym mniejszy rabat
 * dodatkowy może udzielić handlowiec — klient na najniższej cenie ma
 * limit zerowy, bo już ma najniższą cenę.
 *
 * @property string $name
 * @property Section $section
 * @property bool $is_default
 * @property int $position
 * @property bool $is_active
 */
class PriceSection extends Dateable
{
    protected $table = 'price_sections';

    protected $fillable = ['name', 'section', 'position', 'is_default', 'is_active', 'legacy_id'];

    protected function casts(): array
    {
        return ['section' => Section::class, 'position' => 'integer', 'legacy_id' => 'integer'];
    }

    /** @return HasMany<RoleDiscountLimit, $this> */
    public function discountLimits(): HasMany
    {
        return $this->hasMany(RoleDiscountLimit::class, 'price_section_id', 'id');
    }

    /** @return HasMany<PriceListItem, $this> */
    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'price_section_id', 'id');
    }
}
