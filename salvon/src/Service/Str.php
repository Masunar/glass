<?php

declare(strict_types=1);

namespace Salvon\Service;

use Illuminate\Support\Str as LaravelStr;

class Str extends LaravelStr
{
    public static function normalize(string $value): string
    {
        return transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
    }

    public static function toFilename(string $value): array
    {
        $value = self::normalize($value);

        return str_replace([' ', '/', "\\"], ['_', '', ''], $value);
    }

    public static function camelToArray(string $value): array
    {
        return preg_split(pattern: '/(?=[A-Z])/', subject: $value, flags: PREG_SPLIT_NO_EMPTY);
    }

    public static function prefixFromController(string $className): string
    {
        $className = class_basename($className);
        $parts = map(self::camelToArray($className), static fn(string $part): string => strtolower($part));

        $parts = array_diff($parts, ['controller']);
        return implode('_', map($parts, static fn(string $p) => self::plural($p)));
    }

    public static function programmingCases(string $str, bool $kebab = true): array
    {
        $arr = [
            self::camel($str),
            self::pascal($str),
            self::snake($str),
        ];

        if ($kebab) {
            $arr[] = self::kebab($str);
        }

        return $arr;
    }
}
