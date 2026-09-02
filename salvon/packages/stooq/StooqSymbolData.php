<?php

declare(strict_types=1);

namespace Salvon\Stooq;

use Carbon\Carbon;

final readonly class StooqSymbolData
{
    public function __construct(
        public string $name,
        public string $symbol,
        public float $value,
        public float $change,
        public string $changePercent,
        public Carbon $dateTime,
    ) {}
}
