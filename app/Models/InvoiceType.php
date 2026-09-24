<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;

/**
 * @property string $name
 * @property int $vat_rate
 * @property bool $is_default
 * @property int $position
 * @property bool $is_active
 */
class InvoiceType extends Dateable
{

    /**
     * Zmiana stawki w słowniku zmienia brutto każdego zlecenia z tym
     * typem faktury. Patrz `OrderValueStore`.
     */
    protected static function booted(): void
    {
        static::saved(static function (self $type): void {
            if ($type->wasChanged('vat_rate')) {
                Order::query()->where('invoice_type_id', $type->getKey())->update(['value_stale' => true]);
            }
        });
    }

    protected $table = 'invoice_types';

    protected $fillable = ['name', 'vat_rate', 'is_default', 'is_active', 'position', 'legacy_id'];

    protected function casts(): array
    {
        return ['vat_rate' => 'integer', 'position' => 'integer', 'legacy_id' => 'integer'];
    }
}
