<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\DeliveryMethod;
use App\Enum\InvestmentType;
use App\Enum\OrderRelationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zlecenie — oś systemu.
 *
 * @property int $number
 * @property int|null $contractor_id
 * @property int $status_id
 * @property int|null $location_id
 * @property int|null $parent_order_id
 * @property OrderRelationType|null $relation_type
 * @property DeliveryMethod $delivery_method
 * @property int|null $pickup_location_id
 * @property string|null $delivery_address
 * @property string|null $delivery_contact
 * @property bool $is_on_hold
 * @property string|null $hold_reason
 * @property bool $has_open_claim
 * @property string|null $short_note
 * @property string|null $production_comment
 * @property string|null $installer_comment
 * @property string|null $offer_comment
 * @property Carbon|null $client_deadline
 * @property Carbon|null $production_deadline
 * @property Carbon|null $shifted_deadline
 * @property int|null $invoice_type_id
 * @property string|null $buyer_name
 * @property string|null $buyer_tax_id
 * @property string|null $buyer_address
 * @property string|null $accounting_note
 * @property InvestmentType|null $investment_type
 * @property string|null $investment_area_m2
 * @property string|null $shift_reason
 * @property string|null $cancellation_reason
 * @property int|null $created_by
 * @property int|null $owner_id
 * @property int|null $credit_override_by
 * @property Carbon|null $credit_override_at
 * @property string|null $credit_override_reason
 * @property string|null $value_net
 * @property string|null $value_gross
 * @property bool $value_stale
 * @property int|null $drawings_complete_by
 * @property Carbon|null $drawings_complete_at
 * @property-read Collection<int, OrderList> $lists
 * @property-read Collection<int, OrderDiscount> $discounts
 * @property-read Contractor|null $contractor
 * @property-read Status|null $status
 * @property-read Location|null $pickupLocation
 * @property-read User|null $creator
 * @property-read User|null $owner
 * @property-read User|null $creditOverrider
 * @property-read InvoiceType|null $invoiceType
 * @property-read Collection<int, OrderDrawing> $drawings
 * @property-read Collection<int, Payment> $payments
 */
class Order extends Dateable
{

    /**
     * Pola zlecenia, od których zależy jego wartość: typ faktury
     * (stawka), inwestycja mieszkaniowa (podział stawki). Zmiana
     * któregokolwiek oznacza zapamiętaną kwotę jako nieaktualną —
     * w tym samym zapisie, więc nie ma chwili, w której byłaby stara
     * i wyglądała na aktualną. Patrz `OrderValueStore`.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $order): void {
            if ($order->isDirty(['invoice_type_id', 'investment_type', 'investment_area_m2'])) {
                $order->value_stale = true;
            }
        });
    }

    protected $table = 'orders';

    protected $fillable = [
        'number', 'contractor_id', 'status_id', 'location_id',
        'parent_order_id', 'relation_type',
        'delivery_method', 'pickup_location_id', 'delivery_address', 'delivery_contact',
        'invoice_type_id', 'buyer_name', 'buyer_tax_id', 'buyer_address', 'accounting_note',
        'investment_type', 'investment_area_m2',
        'is_on_hold', 'hold_reason', 'has_open_claim', 'agreed_contact_on',
        'short_note', 'production_comment', 'installer_comment', 'offer_comment',
        'client_deadline', 'production_deadline', 'shifted_deadline',
        'shift_reason', 'shift_approved_by', 'cancellation_reason',
        'created_by', 'owner_id', 'measurement_id',
        'drawings_complete_by', 'drawings_complete_at',
        'credit_override_by', 'credit_override_at', 'credit_override_reason',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'relation_type' => OrderRelationType::class,
            'delivery_method' => DeliveryMethod::class,
            'investment_type' => InvestmentType::class,
            'is_on_hold' => 'boolean',
            'has_open_claim' => 'boolean',
            'agreed_contact_on' => 'date',
            'client_deadline' => 'date',
            'production_deadline' => 'date',
            'shifted_deadline' => 'date',
            'drawings_complete_at' => 'datetime',
            'credit_override_at' => 'datetime',
            'value_net' => 'decimal:2',
            'value_gross' => 'decimal:2',
            'value_stale' => 'boolean',
        ];
    }

    /**
     * Termin, który obowiązuje: przesunięty, jeśli uzgodniono zmianę.
     *
     * Stary system liczył opóźnienie od pierwotnej daty, mimo że nowy
     * termin był uzgodniony z klientem i zapisany w komentarzu. Operator
     * uczył się ignorować czerwone pola.
     */
    public function effectiveDeadline(): ?Carbon
    {
        return $this->shifted_deadline ?? $this->client_deadline;
    }

    /** Numer w formie, w jakiej posługuje się nim klient. */
    public function label(): string
    {
        return '#' . $this->number;
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'contractor_id', 'id');
    }

    /** @return BelongsTo<Status, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    /** @return HasMany<OrderList, $this> */
    public function lists(): HasMany
    {
        return $this->hasMany(OrderList::class, 'order_id', 'id');
    }

    /** @return HasMany<OrderDiscount, $this> */
    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class, 'order_id', 'id');
    }
    /**
     * Punkt odbioru ma sens wyłącznie przy odbiorze własnym — przy
     * montażu i dowozie jedziemy do klienta.
     *
     * @return BelongsTo<Location, $this>
     */
    /** @return BelongsTo<Location, $this> */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id', 'id');
    }

    /**
     * Rysunki zlecenia. Produkcja nie ruszy bez kompletu — a o tym, czy
     * komplet jest, decyduje człowiek, nie liczba plików.
     *
     * @return HasMany<OrderDrawing, $this>
     */
    /** @return HasMany<OrderDrawing, $this> */
    public function drawings(): HasMany
    {
        return $this->hasMany(OrderDrawing::class, 'order_id', 'id');
    }

    /**
     * Wpłaty do zlecenia — razem z korektami, bo storno jest zwykłym
     * wierszem z kwotą ujemną.
     *
     * @return HasMany<Payment, $this>
     */
    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id', 'id');
    }

    /**
     * Typ faktury rozstrzyga stawkę VAT. Bez niego karta nie pokazuje
     * kwoty brutto — domyślne 23 % byłoby zgadywaniem stawki na
     * dokumencie księgowym.
     *
     * @return BelongsTo<InvoiceType, $this>
     */
    /** @return BelongsTo<InvoiceType, $this> */
    public function invoiceType(): BelongsTo
    {
        return $this->belongsTo(InvoiceType::class, 'invoice_type_id', 'id');
    }

    /**
     * Kto założył zlecenie. Zapis historyczny — nie zmienia się nigdy
     * i nie mówi, kogo pytać o zlecenie dzisiaj.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Prowadzący — osoba odpowiedzialna za zlecenie teraz. Lista
     * pokazuje jego inicjały przy kolumnie „co dalej", a filtr „moje"
     * i pulpit czytają wyłącznie to pole.
     *
     * Pusty prowadzący jest możliwy tylko wtedy, gdy konto zostało
     * skasowane — zakładanie zlecenia zawsze kogoś wpisuje.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Kto zgodził się przekazać zlecenie na produkcję mimo przekroczonego
     * limitu kupieckiego.
     *
     * @return BelongsTo<User, $this>
     */
    public function creditOverrider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_override_by', 'id');
    }

}
