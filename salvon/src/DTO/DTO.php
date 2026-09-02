<?php

declare(strict_types=1);

namespace Salvon\DTO;

use Salvon\Contract\DataTransferObject;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
abstract class DTO extends Data implements DataTransferObject {}
