<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Section;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Grupa asortymentowa — FLOAT, LUSTRA, VSG, seria okuć CDA-ETNA…
 *
 * Producent i seria są osobnymi polami. Stary system trzymał je razem
 * w jednym polu tekstowym („CDA - ETNA”), a ręczne numery porządkowe
 * kolidowały ze sobą, czyniąc kolejność niedeterministyczną.
 *
 * @property Section $section
 * @property string $name
 * @property string|null $manufacturer
 * @property string|null $series
 * @property int $position
 * @property string|null $comment
 * @property bool $is_active
 */
class ProductGroup extends Dateable
{
    protected $table = 'product_groups';

    protected $fillable = [
        'section', 'name', 'manufacturer', 'series',
        'position', 'comment', 'is_active', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['section' => Section::class, 'position' => 'integer', 'legacy_id' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_group_id', 'id');
    }
}
