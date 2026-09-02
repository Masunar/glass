<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use App\Enum\Section;
use App\Enum\AddressKind;
use Salvon\Model\Dateable;
use App\Enum\ContractorType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kartoteka klientów — firmy i osoby prywatne w jednej tabeli.
 *
 * Kontrahent niesie trzy rzeczy, które decydują o przebiegu zlecenia:
 * jakie ceny widzi (sekcje cenowe i ceny indywidualne), na jakich
 * warunkach kupuje (dni na zapłatę, limit kredytu) i ile już jest
 * winien.
 *
 * @property ContractorType $type
 * @property string $name
 * @property string|null $short_name
 * @property string|null $tax_id
 * @property string|null $registry_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $note
 * @property int $payment_days
 * @property string $credit_limit
 * @property bool $is_supplier
 * @property bool $is_active
 * @property Carbon|null $registered_on
 * @property int|null $created_by
 * @property-read Collection<int, ContractorAddress> $addresses
 * @property-read Collection<int, ContractorContact> $contacts
 * @property-read Collection<int, ContractorPriceSection> $priceSections
 * @property-read Collection<int, ContractorPrice> $prices
 * @property-read ContractorContact|null $primaryContact
 */
class Contractor extends Dateable
{
    protected $table = 'contractors';

    protected $fillable = [
        'type', 'name', 'short_name', 'tax_id', 'registry_id',
        'first_name', 'last_name', 'phone', 'email', 'website',
        'payment_days', 'credit_limit', 'is_supplier', 'is_active',
        'note', 'registered_on', 'created_by', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContractorType::class,
            'payment_days' => 'integer',
            'credit_limit' => 'decimal:2',
            'is_supplier' => 'boolean',
            'is_active' => 'boolean',
            'registered_on' => 'date',
            'legacy_id' => 'integer',
        ];
    }

    /** Nazwa do list i wydruków — pełne nazwy spółek są nieczytelne. */
    public function displayName(): string
    {
        return $this->short_name !== null && $this->short_name !== ''
            ? $this->short_name
            : $this->name;
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ContractorAddress::class, 'contractor_id', 'id');
    }

    public function addressOf(AddressKind $kind): ?ContractorAddress
    {
        /** @var ContractorAddress|null */
        return $this->addresses()->where('kind', $kind->value)->first();
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ContractorContact::class, 'contractor_id', 'id');
    }

    public function primaryContact(): HasOne
    {
        // where() na relacji zwraca Buildera, nie relacje, wiec warunek
        // nakladamy osobno i oddajemy sama relacje.
        $relation = $this->hasOne(ContractorContact::class, 'contractor_id', 'id');
        $relation->where('is_primary', true);

        return $relation;
    }

    public function priceSections(): HasMany
    {
        return $this->hasMany(ContractorPriceSection::class, 'contractor_id', 'id');
    }

    /**
     * Sekcja cenowa przypisana dla danej sekcji asortymentu.
     *
     * Brak przypisania nie blokuje wyceny — wtedy obowiązuje sekcja
     * domyślna (K-02). Blokada oznaczałaby, że nowy klient nie da się
     * wycenić, dopóki ktoś nie uzupełni pięciu wierszy.
     */
    public function priceSectionFor(Section $section): ?PriceSection
    {
        /** @var ContractorPriceSection|null $assignment */
        $assignment = $this->priceSections()
            ->where('section', $section->value)
            ->first();

        return $assignment?->priceSection;
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ContractorPrice::class, 'contractor_id', 'id');
    }
}
