<?php

declare(strict_types=1);

namespace Salvon\Eloquent\Casts\Date;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

trait CastCreatedAt
{
    public function getCreatedAtAttribute(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::createFromDate($value)->format(Config::get('salvon.framework.default_cast_date_format'));
    }
}
