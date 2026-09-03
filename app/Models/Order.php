<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\DeliveryMethod;
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
 * @property int|null $created_by
 * @property-read Collection<int, OrderList> $lists
 * @property-read Collection<int, OrderDiscount> $discounts
 * @property-read Contractor|null $contractor
 * @property-read Status|null $status
 * @property-read Location|null $pickupLocation
 * @property-read User|null $creator
 */
class Order extends Dateable
{
    protected $table = 'orders';

    protected $fillable = [
        'number', 'contractor_id', 'status_id', 'location_id',
        'parent_order_id', 'relation_type',
        'delivery_method', 'pickup_location_id', 'delivery_address', 'delivery_contact',
        'invoice_type_id', 'buyer_name', 'buyer_tax_id', 'buyer_address', 'accounting_note',
        'is_on_hold', 'hold_reason', 'has_open_claim', 'agreed_contact_on',
        'short_note', 'production_comment', 'installer_comment', 'offer_comment',
        'client_deadline', 'production_deadline', 'shifted_deadline',
        'shift_reason', 'shift_approved_by', 'cancellation_reason',
        'created_by', 'measurement_id',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'relation_type' => OrderRelationType::class,
            'delivery_method' => DeliveryMethod::class,
            'is_on_hold' => 'boolean',
            'has_open_claim' => 'boolean',
            'agreed_contact_on' => 'date',
            'client_deadline' => 'date',
            'production_deadline' => 'date',
            'shifted_deadline' => 'date',
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
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id', 'id');
    }

    /**
     * Handlowiec prowadzący zlecenie. Lista pokazuje jego inicjały przy
     * kolumnie „co dalej" — decyzja ma mieć właściciela.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

}
