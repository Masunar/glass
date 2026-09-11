<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wpłata do zlecenia.
 *
 * Kwota jest zapisana dwa razy: w walucie wpłaty i po przeliczeniu na
 * walutę rozliczeniową. Przeliczona jest **zapisana, nie liczona przy
 * odczycie** — kurs z dnia wpłaty nie może się zmienić dlatego, że ktoś
 * jutro poprawi tabelę kursów.
 *
 * Kurs podaje człowiek. Księgowa i tak przepisuje go z wyciągu
 * bankowego, a automat z zewnętrznej tabeli dawałby kwotę inną niż na
 * dokumencie, po którym pieniądze faktycznie weszły.
 *
 * @property int $order_id
 * @property int $cash_register_id
 * @property string $amount
 * @property string $currency
 * @property string $exchange_rate
 * @property string $amount_base
 * @property Carbon $paid_on
 * @property string|null $note
 * @property int|null $reversal_of_id
 * @property int|null $created_by
 * @property-read Order|null $order
 * @property-read CashRegister|null $cashRegister
 * @property-read Payment|null $reverses
 * @property-read Payment|null $reversedBy
 * @property-read User|null $creator
 */
class Payment extends Dateable
{
    protected $table = 'payments';

    protected $fillable = [
        'order_id', 'cash_register_id', 'amount', 'currency',
        'exchange_rate', 'amount_base', 'paid_on', 'note',
        'reversal_of_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'amount_base' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    /** Korekta odwracająca inną wpłatę — kwota ujemna. */
    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /** @return BelongsTo<CashRegister, $this> */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id', 'id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'reversal_of_id', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
