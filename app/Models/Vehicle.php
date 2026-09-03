<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pojazd floty. `payload_kg` to dopuszczalna masa ładunku (DMZ).
 *
 * Domyka łańcuch: gęstość szkła → waga formatki → waga zlecenia → dobór
 * auta. W starym systemie wszystkie ogniwa istniały, ale waga formatki
 * nie była liczona, więc dobór auta odbywał się na oko — i zdarzało się
 * zlecenie cięższe niż ładowność najcięższego pojazdu.
 *
 * @property string $name
 * @property string|null $short_name
 * @property int $payload_kg
 * @property int $crew_slots
 * @property int $position
 * @property bool $is_active
 */
class Vehicle extends Dateable
{
    protected $table = 'vehicles';

    protected $fillable = [
        'name', 'short_name', 'payload_kg', 'location_id',
        'crew_slots', 'is_active', 'position', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'payload_kg' => 'integer',
            'crew_slots' => 'integer',
            'position' => 'integer',
            'legacy_id' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }
}
