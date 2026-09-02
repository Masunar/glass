<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Salvon\DTO\DTO;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class UpdateProfileDTO extends DTO
{
    public function __construct(
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
    ) {}
}
