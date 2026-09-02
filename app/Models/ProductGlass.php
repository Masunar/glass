<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parametry szklane produktu — „mix” w języku domenowym.
 *
 * @property int $product_id
 * @property float $thickness_mm
 * @property string|null $variant
 * @property bool $is_tempered_by_default
 */
class ProductGlass extends Model
{
    /**
     * Gęstość szkła: 2500 kg/m³, czyli 2,5 kg na metr kwadratowy
     * na każdy milimetr grubości.
     *
     * Zależność potwierdzona co do wiersza na całym słowniku materiałów
     * starego systemu.
     */
    public const DENSITY_KG_PER_M2_PER_MM = 2.5;

    public $timestamps = false;

    protected $table = 'product_glass';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = ['product_id', 'thickness_mm', 'variant', 'is_tempered_by_default'];

    protected function casts(): array
    {
        return [
            'thickness_mm' => 'float',
            'is_tempered_by_default' => 'boolean',
        ];
    }

    /**
     * Waga metra kwadratowego. Wartość wyliczana, nie kolumna —
     * w starym systemie ta sama wielkość była przechowywana i wyświetlana
     * na trzy różne sposoby w trzech modułach.
     */
    public function weightPerM2(): float
    {
        return $this->thickness_mm * self::DENSITY_KG_PER_M2_PER_MM;
    }

    /** Waga formatki o podanych wymiarach w milimetrach. */
    public function weightOfPane(int $widthMm, int $heightMm, int $quantity = 1): float
    {
        $squareMeters = ($widthMm / 1000) * ($heightMm / 1000) * $quantity;

        return round($squareMeters * $this->weightPerM2(), 2);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
