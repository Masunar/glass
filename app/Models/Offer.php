<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\OfferStatus;
use App\Enum\OfferSumMode;
use App\Enum\OfferDetailLevel;
use App\Enum\OfferPriceDisplay;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oferta — zapisany dokument, nie wydruk składany w locie.
 *
 * `snapshot` jest treścią oferty i **nigdy nie jest przeliczany**.
 * Zlecenie żyje dalej: zmienia się wycena, dochodzą formatki, rusza
 * rabat. Oferta zostaje przy tym, co poszło do klienta — inaczej
 * pytanie „co mu wysłaliśmy w zeszłym tygodniu" nie ma odpowiedzi.
 *
 * @property int $order_id
 * @property int $sequence
 * @property OfferStatus $status
 * @property OfferDetailLevel $detail_level
 * @property OfferSumMode $sum_mode
 * @property OfferPriceDisplay $price_display
 * @property bool $is_variant
 * @property Carbon|null $valid_until
 * @property string $net
 * @property string|null $vat
 * @property string|null $gross
 * @property array<string, mixed> $snapshot
 * @property string|null $comment
 * @property string|null $rejection_reason
 * @property int|null $accepted_list_id
 * @property int|null $issued_by
 * @property Carbon $issued_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $decided_at
 * @property-read Order $order
 * @property-read User|null $issuer
 */
class Offer extends Dateable
{
    protected $table = 'offers';

    protected $fillable = [
        'order_id', 'sequence', 'status', 'detail_level', 'sum_mode',
        'price_display', 'is_variant', 'valid_until',
        'net', 'vat', 'gross', 'snapshot', 'comment', 'rejection_reason',
        'accepted_list_id', 'issued_by', 'issued_at', 'sent_at', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => OfferStatus::class,
            'detail_level' => OfferDetailLevel::class,
            'sum_mode' => OfferSumMode::class,
            'price_display' => OfferPriceDisplay::class,
            'is_variant' => 'boolean',
            'valid_until' => 'date',
            // Jawny ksztalt kwoty, jak w `OrderItem`. Bez tego castu
            // to, czy `net` wraca jako '1000.00' czy 1000, zalezy od
            // sterownika bazy — a porownania kwot sa w calym systemie
            // napisowe.
            'net' => 'decimal:2',
            'vat' => 'decimal:2',
            'gross' => 'decimal:2',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Numer widoczny dla klienta: `24046/1`.
     *
     * Składany, a nie przechowywany — numer zlecenia leży w bazie raz
     * i nie ma powodu przepisywać go w drugie miejsce, gdzie mógłby
     * się rozjechać.
     */
    public function number(): string
    {
        // Bez `?->`: `offers.order_id` jest NOT NULL z kluczem obcym,
        // wiec oferta bez zlecenia nie istnieje. Domyslka bronilaby
        // przed przypadkiem, ktorego baza nie dopuszcza.
        return $this->order->number . '/' . $this->sequence;
    }

    /** Czy oferta straciła ważność, nie doczekawszy się decyzji. */
    public function isExpired(?Carbon $on = null): bool
    {
        if ($this->valid_until === null || !$this->status->isOpen()) {
            return false;
        }

        return $this->valid_until->lt($on ?? Carbon::today());
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by', 'id');
    }

    /** @return BelongsTo<OrderList, $this> */
    public function acceptedList(): BelongsTo
    {
        return $this->belongsTo(OrderList::class, 'accepted_list_id', 'id');
    }
}
