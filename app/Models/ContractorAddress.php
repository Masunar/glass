<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\AddressKind;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Adres kontrahenta w jednej z trzech ról.
 *
 * Rozbity na pola, a nie trzymany jako blok tekstu — dzięki temu da się
 * planować trasy dostaw i filtrować po mieście.
 *
 * @property int $contractor_id
 * @property AddressKind $kind
 * @property string $country
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $street
 * @property string|null $building_number
 * @property string|null $unit_number
 * @property string|null $voivodeship
 * @property string|null $county
 * @property string|null $post_office
 */
class ContractorAddress extends Dateable
{
    protected $table = 'contractor_addresses';

    protected $fillable = [
        'contractor_id', 'kind', 'country', 'voivodeship', 'county',
        'post_office', 'city', 'postal_code', 'street',
        'building_number', 'unit_number',
    ];

    protected function casts(): array
    {
        return ['kind' => AddressKind::class];
    }

    /** Adres w jednej linii — do list, wydruków i wyszukiwarki. */
    public function oneLine(): string
    {
        $building = trim(sprintf(
            '%s%s',
            $this->building_number ?? '',
            $this->unit_number !== null ? '/' . $this->unit_number : '',
        ));

        $street = trim(sprintf('%s %s', $this->street ?? '', $building));
        $city = trim(sprintf('%s %s', $this->postal_code ?? '', $this->city ?? ''));

        return trim(implode(', ', array_filter([$street, $city])), ', ');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'contractor_id', 'id');
    }
}
