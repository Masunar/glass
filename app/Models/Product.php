<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Unit;
use Carbon\Carbon;
use App\Enum\Section;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kartoteka produktów — jedna dla wszystkich pięciu sekcji.
 *
 * Cennik, ceny indywidualne kontrahentów, rabaty na zleceniu i stany
 * magazynowe odnoszą się do jednego bytu, więc jedna tabela z polem
 * `section` jest prostsza niż pięć równoległych kartotek. Pola właściwe
 * dla sekcji siedzą w rozszerzeniach 1:1.
 *
 * @property int $product_group_id
 * @property Section $section
 * @property string|null $code
 * @property string|null $manufacturer_code
 * @property string $name
 * @property Unit $unit
 * @property int $vat_rate
 * @property bool $is_made_to_order
 * @property bool $is_active
 * @property-read ProductGroup|null $group
 * @property-read ProductGlass|null $glass
 * @property-read ProductFitting|null $fitting
 * @property-read ProductService|null $service
 */
class Product extends Dateable
{
    protected $table = 'products';

    protected $fillable = [
        'product_group_id', 'section', 'code', 'manufacturer_code', 'name',
        'unit', 'vat_rate', 'is_made_to_order', 'is_active', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'section' => Section::class,
            'unit' => Unit::class,
            'vat_rate' => 'integer',
            'is_made_to_order' => 'boolean',
            'legacy_id' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id', 'id');
    }

    public function glass(): HasOne
    {
        return $this->hasOne(ProductGlass::class, 'product_id', 'id');
    }

    public function fitting(): HasOne
    {
        return $this->hasOne(ProductFitting::class, 'product_id', 'id');
    }

    public function service(): HasOne
    {
        return $this->hasOne(ProductService::class, 'product_id', 'id');
    }

    public function purchasePrices(): HasMany
    {
        return $this->hasMany(PurchasePrice::class, 'product_id', 'id');
    }

    public function priceListItems(): HasMany
    {
        return $this->hasMany(PriceListItem::class, 'product_id', 'id');
    }

    /** Cena zakupu obowiązująca w danym dniu. */
    public function purchasePriceAt(?Carbon $date = null): ?PurchasePrice
    {
        $date ??= Carbon::today();

        /** @var PurchasePrice|null */
        return PurchasePrice::query()
            ->where('product_id', $this->id)
            ->whereDate('valid_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /** @param Builder<self> $query */
    public function scopeSection(Builder $query, Section $section): Builder
    {
        return $query->where('section', $section->value);
    }
}
