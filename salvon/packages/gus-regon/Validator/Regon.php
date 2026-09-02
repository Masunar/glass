<?php

declare(strict_types=1);

namespace Salvon\Regon\Validator;

use InvalidArgumentException;

final readonly class Regon
{
    /** @param string[] | string $value */
    public static function validate(string|array $value): void
    {
        if(is_string($value)) {
            $value = [$value];
        }

        foreach ($value as $nip) {
            if(self::isValid($nip)) {
                continue;
            }

            throw new InvalidArgumentException(
                sprintf('REGON number %s is invalid, available types: REGON-9, REGON-14', $nip),
            );
        }
    }

    public static function isValid(string $value): bool
    {
        $matchResult = preg_match('/^(\d{9}|\d{14})$/', $value);
        if ($matchResult === false || $matchResult === 0) {
            return false;
        }

        if (strlen($value) === 9) {
            return self::hasProperChecksumForShort($value);
        }

        return self::hasProperChecksumForShort(substr($value, 0, 9))
            && self::hasProperChecksumForLong($value);
    }

    private static function hasProperChecksumForShort(string $value): bool
    {
        $chars = str_split($value);
        $sum = array_sum(
            array_map(
                static fn(int $weight, string $digit): int =>  $weight * (int) $digit,
                [8, 9, 2, 3, 4, 5, 6, 7],
                array_slice($chars, 0, 8),
            ),
        );

        $checksum = $sum % 11;

        return $checksum % 10 === (int) $chars[8];
    }

    private static function hasProperChecksumForLong(string $value): bool
    {
        $chars = str_split($value);
        $sum = array_sum(
            array_map(
                static fn(int $weight, string $digit): int => $weight * (int) $digit,
                [2, 4, 8, 5, 0, 9, 7, 3, 6, 1, 2, 4, 8],
                array_slice($chars, 0, 13),
            ),
        );

        $checksum = $sum % 11;

        return $checksum % 10 === (int) $chars[13];
    }
}
