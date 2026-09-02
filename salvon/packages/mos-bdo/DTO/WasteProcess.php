<?php

namespace Salvon\BDO\DTO;

final readonly class WasteProcess
{
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $parent = null,
        public ?string $codeName = null,
        public ?string $type = null,
    )
    {}
}
