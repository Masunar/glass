<?php

declare(strict_types=1);

namespace Salvon\UPS\DTO;

final readonly class CredentialsData
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        public string $accountNumber,
        public bool   $sandbox = false,
    ) {}
}
