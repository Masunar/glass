<?php

declare(strict_types=1);

namespace Salvon\Regon\Data;

/**
 * @template T
 */
final readonly class Collection
{
    public function __construct(
        /** @var array<T> */
        public array $items = [],
    ) {}

    /** @return array<T> */
    public function toArray(): array
    {
        return $this->items;
    }

    /** @return null|T */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }
}
