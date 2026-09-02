<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pozycja cennika: produkt × sekcja cenowa.
 *
 * Wartością wiodącą jest współczynnik narzutu, a nie kwota — cennik jest
 * polityką marży, nie tabelą liczb. Cena wynikowa to
 * `cena zakupu × współczynnik`, zależność potwierdzona co do grosza
 * na danych produkcyjnych starego systemu.
 *
 * @property int $product_id
 * @property int $price_section_id
 * @property string $coefficient
 * @property string|null $computed_net_price
 * @property string|null $manual_net_price
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class PriceListItem extends Dateable
{
    protected $table = 'price_list_items';

    protected $fillable = [
        'product_id', 'price_section_id', 'coefficient',
        'computed_net_price', 'manual_net_price',
        'valid_from', 'valid_to', 'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:4',
            'computed_net_price' => 'decimal:2',
            'manual_net_price' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /** Cena obowiązująca: ręczna, jeśli nadana, w przeciwnym razie wyliczona. */
    public function effectiveNetPrice(): ?string
    {
        return $this->manual_net_price ?? $this->computed_net_price;
    }

    /**
     * Świadomie bez bcmath: rozszerzenie nie jest wymagane w composer.json
     * ani kompilowane w obrazie aplikacji. Przy kwotach tego rzędu błąd
     * reprezentacji jest o rzędy wielkości mniejszy od grosza, a wynik
     * i tak zaokrąglamy do dwóch miejsc przed zapisem.
     */
    public static function computePrice(string $purchaseNetPrice, string $coefficient): string
    {
        return number_format(round((float) $purchaseNetPrice * (float) $coefficient, 2), 2, '.', '');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function priceSection(): BelongsTo
    {
        return $this->belongsTo(PriceSection::class, 'price_section_id', 'id');
    }
}
