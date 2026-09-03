<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\StatusDomain;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dozwolone przejście między statusami wraz z warunkami.
 *
 * Warunki są danymi, nie kodem — dzięki temu zablokowane przejście
 * potrafi odpowiedzieć użytkownikowi, czego dokładnie brakuje.
 * `from_status_id` równe null oznacza przejście początkowe, czyli
 * nadanie pierwszego statusu.
 *
 * @property StatusDomain $domain
 * @property int|null $from_status_id
 * @property int $to_status_id
 * @property array|null $conditions
 * @property string|null $button_label
 * @property string|null $required_permission
 * @property int $position
 * @property bool $is_active
 */
class StatusTransition extends Dateable
{
    protected $table = 'status_transitions';

    protected $fillable = [
        'domain',
        'from_status_id',
        'to_status_id',
        'conditions',
        'button_label',
        'required_permission',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'domain' => StatusDomain::class,
            'conditions' => 'array',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Status, $this> */
    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'from_status_id', 'id');
    }

    /** @return BelongsTo<Status, $this> */
    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'to_status_id', 'id');
    }
}
