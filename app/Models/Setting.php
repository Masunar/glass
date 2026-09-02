<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Model;

/**
 * @property string $key
 * @property string|null $value
 * @property string $type
 */
class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    public function castValue(): mixed
    {
        return match ($this->type) {
            'float'   => (float) $this->value,
            'int'     => (int) $this->value,
            'bool'    => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            default   => $this->value,
        };
    }
}
