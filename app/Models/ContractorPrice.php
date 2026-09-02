<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Poziom 3: cena indywidualna kontrahenta dla konkretnego produktu.
 *
 * Wersjonowana datami obowiązywania — bez nich promocja wynegocjowana
 * na jeden sezon zostaje w systemie na zawsze, a nikt nie pamięta,
 * dlaczego ten klient ma taką cenę.
 *
 * @property int $contractor_id
 * @property int $product_id
 * @property string $net_price
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class ContractorPrice extends Dateable
{
    protected $table = 'contractor_prices';

    protected $fillable = [
        'contractor_id', 'product_id', 'net_price',
        'valid_from', 'valid_to', 'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'net_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'contractor_id', 'id');
    }
}
