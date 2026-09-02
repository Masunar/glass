<?php

declare(strict_types=1);

namespace Salvon\Eloquent\Casts;

use Salvon\Eloquent\Casts\Date\CastCreatedAt;
use Salvon\Eloquent\Casts\Date\CastDeletedAt;
use Salvon\Eloquent\Casts\Date\CastUpdatedAt;

trait CastBuiltInDates
{
    use CastCreatedAt;
    use CastUpdatedAt;
    use CastDeletedAt;
}
