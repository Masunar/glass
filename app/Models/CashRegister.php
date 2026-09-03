<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use App\Enum\PaymentChannel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kasa: miejsce albo osoba przyjmująca wpłatę.
 *
 * Kanał i waluta są osobnymi wymiarami na wpłacie. Stary słownik
 * „Typy wpłat" mieszał wszystkie trzy w jednej liście, więc każda nowa
 * kombinacja tworzyła nową pozycję, a raport kasowy per punkt był
 * ręcznym sumowaniem.
 *
 * @property string $name
 * @property int|null $location_id
 * @property int|null $user_id
 * @property PaymentChannel $channel
 * @property string $default_currency
 * @property int $position
 * @property bool $is_active
 */
class CashRegister extends Dateable
{
    protected $table = 'cash_registers';

    protected $fillable = [
        'name', 'location_id', 'user_id', 'channel',
        'default_currency', 'is_active', 'position', 'legacy_id',
    ];

    protected function casts(): array
    {
        return ['channel' => PaymentChannel::class, 'position' => 'integer', 'legacy_id' => 'integer'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
