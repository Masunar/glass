<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Service;

use BackedEnum;

final readonly class Value
{
    public static function strOrNull(?string $val): ?string
    {
        return is_null($val) ? null : strval($val);
    }

    /**
     * @param string|null $val
     * @param class-string<BackedEnum> $enumClass
     * @return BackedEnum|null
     */
    public static function tryEnumOrNull(null|string|int $val, string $enumClass): ?BackedEnum
    {
        return (is_null($val))
            ? null
            : $enumClass::tryFrom($val);
    }
}
