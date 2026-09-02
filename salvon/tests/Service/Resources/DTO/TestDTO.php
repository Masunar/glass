<?php

declare(strict_types=1);

namespace Salvon\Tests\Service\Resources\DTO;

use Salvon\DTO\DTO;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class TestDTO extends DTO
{
    public function __construct(
        public readonly string           $key,
        public readonly string|int|float $value,
    ) {}
}
