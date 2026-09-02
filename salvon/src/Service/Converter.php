<?php

declare(strict_types=1);

namespace Salvon\Service;

final class Converter
{
    /**
     * @param array<int, mixed> $items
     *
     * @return array<int, mixed>
     */
    public static function arrayItemsToInt(array $items): array
    {
        return array_map(static fn(mixed $item): int => (int) $item, $items);
    }
}
