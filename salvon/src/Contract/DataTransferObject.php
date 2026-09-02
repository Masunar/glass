<?php

declare(strict_types=1);

namespace Salvon\Contract;

interface DataTransferObject
{
    public function toArray(): array;
}
