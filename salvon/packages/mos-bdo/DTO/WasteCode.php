<?php

namespace Salvon\BDO\DTO;

final readonly class WasteCode
{
    public function __construct(
        public int $id,
        public ?string $code = null,
        public ?string $description = null,
        public ?bool $isDangerous = null,
    )
    {}
}
