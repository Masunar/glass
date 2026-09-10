<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maksymalny rabat, jaki dana rola może udzielić na danej sekcji cenowej.
 *
 * Wiersz rola × sekcja, nie kolumna. Stary system miał kolumny dla trzech
 * ról (admin, starszy handlowiec, handlowiec), więc dodanie czwartej roli
 * sprzedażowej wymagałoby zmiany schematu tabeli.
 *
 * @property int $price_section_id
 * @property int $role_id
 * @property string $max_discount_percent
 */
class RoleDiscountLimit extends Dateable
{
    protected $table = 'role_discount_limits';

    protected $fillable = ['price_section_id', 'role_id', 'max_discount_percent'];

    protected function casts(): array
    {
        return ['max_discount_percent' => 'decimal:2'];
    }

    /** @return BelongsTo<PriceSection, $this> */
    public function priceSection(): BelongsTo
    {
        return $this->belongsTo(PriceSection::class, 'price_section_id', 'id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
}
