<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;

/**
 * Dzień wolny zakładu — poza świętami ustawowymi, które liczy kod
 * (`PolishHolidays`). Inwentaryzacja, przestój, długi weekend.
 *
 * @property Carbon $date
 * @property string $name
 * @property bool $is_active
 */
class DayOff extends Dateable
{
    protected $table = 'days_off';

    protected $fillable = ['date', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
