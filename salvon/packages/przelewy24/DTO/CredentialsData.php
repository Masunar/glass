<?php

declare(strict_types=1);

namespace Salvon\Przelewy24\DTO;

final readonly class CredentialsData
{
    public function __construct(
        public int    $merchantId,
        public string $secretId,
        public string $crc,
        public ?int   $posId = null,
    ) {}
}
