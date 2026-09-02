<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Wpis dziennika zmian. Rejestr wyłącznie do dopisywania.
 *
 * Przechowuje wartość przed i po dla każdego zmienionego pola, a zmiany
 * z jednej sesji edycji dzielą `edit_session_id` i są prezentowane jako
 * jeden wpis. Stary log rejestrował sam fakt zmiany („Zmienił rabaty"),
 * co czyniło go bezużytecznym przy dochodzeniu, skąd wzięła się cena.
 *
 * @property string|null $edit_session_id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property int|null $user_id
 * @property string $event
 * @property array|null $changes
 * @property string|null $ip_address
 * @property-read Carbon $created_at
 */
class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_entries';

    protected $fillable = [
        'edit_session_id',
        'auditable_type',
        'auditable_id',
        'user_id',
        'event',
        'changes',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'auditable_type', 'auditable_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
