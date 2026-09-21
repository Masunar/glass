<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dostawca towaru.
 *
 * Osobny slownik, a nie kontrahent z flaga: kartoteka kontrahentow
 * niesie limit kupiecki, sekcje cenowa i limity rabatowe, czyli
 * rzeczy, ktore przy dostawcy nie znacza nic. Firma bedaca
 * jednoczesnie klientem i dostawca wystapi w obu miejscach — to
 * dublowanie nazwy i NIP-u, ale nie dublowanie zachowania.
 *
 * @property string $name
 * @property string|null $short_name
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property int $position
 * @property bool $is_active
 */
class Supplier extends Dateable
{
    protected $table = 'suppliers';

    protected $fillable = [
        'name', 'short_name', 'contact_person', 'phone', 'email',
        'is_active', 'position', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'legacy_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'supplier_id', 'id');
    }
}
