<?php

declare(strict_types=1);

namespace Salvon\Service;

final class Encoder
{
    public static function base64(string $string): string
    {
        return base64_encode($string);
    }

    public static function fromBase64(string $string): string
    {
        return base64_decode($string);
    }

    public static function base64Utf8(string $string): string
    {
        return mb_convert_encoding(self::fromBase64($string), 'UTF-8', 'auto');
    }

    public static function toBase64Utf8(string $string): string
    {
        return self::base64(mb_convert_encoding($string, 'UTF-8'));
    }

    public static function json(mixed $value, int $flags = 0, $depth = 512): ?string
    {
        $value = json_encode($value, $flags, $depth);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    public static function arrayFromJson(string $value, $depth = 512, $flags = 0): array
    {
        return json_decode($value, true, $depth, $flags);
    }

    public static function objectFromJson(string $value, $depth = 512, $flags = 0): object
    {
        return json_decode($value, false, $depth, $flags);
    }

    public static function hashedJson(mixed $value, string $algo = 'md5', int $flags = 0, $depth = 512): string
    {
        return hash($algo, Encoder::json($value, $flags, $depth));
    }

    public static function decodeFile(string $string): string
    {
        if (str_contains($string, ',')) {
            $string = explode(',', $string)[1];
        }

        return base64_decode($string);
    }
}
