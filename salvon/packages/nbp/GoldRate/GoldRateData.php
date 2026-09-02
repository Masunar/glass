<?php

declare(strict_types=1);

namespace Salvon\NBP\GoldRate;

use Carbon\Carbon;

final readonly class GoldRateData
{
    public function __construct(
        public float $value,
        public string $originalDateString,
        public Carbon $date,
    ) {}
}
