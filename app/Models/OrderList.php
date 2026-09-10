<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\ListRole;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lista w zleceniu — najbardziej nietypowy mechanizm w systemie.
 *
 * Obsługuje dwa przypadki naraz: kompozycję (kilka pomieszczeń = kilka
 * list, wszystkie wliczone) i wariantowanie oferty (alternatywy, jedna
 * wliczona, reszta zostaje w historii zlecenia).
 *
 * @property int $order_id
 * @property int $number
 * @property string|null $name
 * @property ListRole $role
 * @property bool $is_included
 * @property bool $is_on_hold
 * @property string|null $comment
 * @property-read Collection<int, OrderItem> $items
 */
class OrderList extends Dateable
{
    protected $table = 'order_lists';

    protected $fillable = [
        'order_id', 'number', 'name', 'role', 'start_type',
        'is_included', 'is_on_hold', 'comment',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'role' => ListRole::class,
            'is_included' => 'boolean',
            'is_on_hold' => 'boolean',
        ];
    }

    /**
     * Czy lista wchodzi do wartości zlecenia.
     *
     * `is_included` i `is_on_hold` to dwie różne rzeczy: wyłączona nie
     * należy do zlecenia (odrzucona alternatywa, kwota zero), wstrzymana
     * należy i jest wyceniona, ale nie może iść na produkcję.
     */
    public function countsTowardsTotal(): bool
    {
        return $this->is_included;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_list_id', 'id');
    }
}
