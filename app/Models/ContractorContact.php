<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Osoba kontaktowa u kontrahenta.
 *
 * Przy sieciach handlowych jedna osoba to za mało — stąd lista,
 * a nie pojedyncze pola telefonu i e-maila na karcie.
 *
 * @property int $contractor_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $position
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_primary
 */
class ContractorContact extends Dateable
{
    protected $table = 'contractor_contacts';

    protected $fillable = [
        'contractor_id', 'first_name', 'last_name',
        'position', 'phone', 'email', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function fullName(): string
    {
        return trim(sprintf('%s %s', $this->first_name ?? '', $this->last_name ?? ''));
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'contractor_id', 'id');
    }
}
