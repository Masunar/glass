<?php

declare(strict_types=1);

namespace Salvon\NBP\ExchangeRate;

use Carbon\Carbon;

final readonly class ExchangeRateData
{
    public function __construct(
        public string $currency,
        public float  $value,
        public Carbon $date,
        public ?string $number = null,
    ) {}
}
