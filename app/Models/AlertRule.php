<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\AlertCategory;
use Salvon\Model\Dateable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Reguła alertu: warunek → etykieta → kolor → moduł.
 *
 * Reguły są danymi edytowalnymi z panelu administratora, nie warunkami
 * w kodzie — dodanie alertu nie wymaga wdrożenia.
 *
 * @property string $code
 * @property string $name
 * @property string $module
 * @property AlertCategory $category
 * @property array $condition
 * @property string $label
 * @property string|null $color
 * @property int $position
 * @property bool $is_active
 */
class AlertRule extends Dateable
{
    protected $table = 'alert_rules';

    protected $fillable = [
        'code',
        'name',
        'module',
        'category',
        'condition',
        'label',
        'color',
        'position',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => AlertCategory::class,
            'condition' => 'array',
            'position' => 'integer',
        ];
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(AlertOccurrence::class, 'alert_rule_id', 'id');
    }
}
