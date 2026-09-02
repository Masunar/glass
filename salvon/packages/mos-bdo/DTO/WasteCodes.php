<?php

namespace Salvon\BDO\DTO;

final readonly class WasteCodes
{
    public function __construct(
        /** @var array<WasteCode> */
        public array $items = [],
    )
    {}
}
