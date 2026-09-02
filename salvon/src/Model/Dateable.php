<?php

declare(strict_types=1);

namespace Salvon\Model;

use Carbon\Carbon;
use Salvon\Eloquent\Casts\CastBuiltInDates;

/**
 * Abstract model extending default Salvon model to handle dates
 *
 * Do not use with Pivot type tables!
 * Pivot Abstraction -> Illuminate\Database\Eloquent\Model
 *
 * @property-read Carbon $created_at;
 * @property-read Carbon $updated_at;
 * @inheritDoc
 */
abstract class Dateable extends Model
{
    /**
     * DateTime casting using automatic Laravel casting (casts() method) causes up to 10 times lower performance.
     * Lack performance shows on larger dataset (for example 500 entries).
     * Use @CastBuiltInDates or accessor casting for much better results.
     */
    use CastBuiltInDates;

    public $timestamps = true;
}
