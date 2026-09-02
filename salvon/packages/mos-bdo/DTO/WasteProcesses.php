<?php

namespace Salvon\BDO\DTO;

final readonly class WasteProcesses
{
    public function __construct(
        /** @var array<WasteProcess> */
        public array $items = [],
    )
    {}
}
