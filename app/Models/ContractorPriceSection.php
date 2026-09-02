<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Section;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Poziom 2 ustalania ceny: sekcja cenowa kontrahenta w danej sekcji
 * asortymentu.
 *
 * @property int $contractor_id
 * @property Section $section
 * @property int $price_section_id
 * @property-read PriceSection|null $priceSection
 */
class ContractorPriceSection extends Dateable
{
    protected $table = 'contractor_price_sections';

    protected $fillable = ['contractor_id', 'section', 'price_section_id', 'changed_by'];

    protected function casts(): array
    {
        return ['section' => Section::class];
    }

    public function priceSection(): BelongsTo
    {
        return $this->belongsTo(PriceSection::class, 'price_section_id', 'id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class, 'contractor_id', 'id');
    }
}
