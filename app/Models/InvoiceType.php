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
    protected $table = 'invoice_types';

    protected $fillable = ['name', 'vat_rate', 'is_default', 'is_active', 'position', 'legacy_id'];

    protected function casts(): array
    {
        return ['vat_rate' => 'integer', 'position' => 'integer', 'legacy_id' => 'integer'];
    }
}
