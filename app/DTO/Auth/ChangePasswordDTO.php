<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Salvon\DTO\DTO;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class ChangePasswordDTO extends DTO
{
    public function __construct(
        public readonly ?string $password,
        public readonly ?string $confirmPassword,
        public readonly ?string $currentPassword = null,
    ) {}
}
