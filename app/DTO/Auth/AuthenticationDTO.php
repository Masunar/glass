<?php

declare(strict_types=1);

namespace App\DTO\Auth;

use Salvon\DTO\DTO;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class AuthenticationDTO extends DTO
{
    public function __construct(
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?string $mfaCode = null,
        public readonly bool    $rememberMe = false,
    ) {}
}
