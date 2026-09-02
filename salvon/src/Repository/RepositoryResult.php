<?php

declare(strict_types=1);

namespace Salvon\Repository;

use Illuminate\Support\Collection;
use Salvon\Contract\DataTransferObject;

readonly class RepositoryResult implements DataTransferObject
{
    public function __construct(
        public int $count,
        public Collection $items,
    ) {}

    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'items' => $this->items,
        ];
    }
}
