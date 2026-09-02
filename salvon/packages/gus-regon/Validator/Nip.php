<?php

declare(strict_types=1);

namespace Salvon\Regon\Validator;

use InvalidArgumentException;

final readonly class Nip
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
                sprintf('NIP number %s is invalid', $nip),
            );
        }
    }

    public static function isValid(string $value): bool
    {
        if ('0000000000' === $value) {
            return false;
        }

        $matchResult = preg_match('/^\d{10}$/', $value);
        if ($matchResult === false || $matchResult === 0) {
            return false;
        }

        $chars = str_split($value);
        $sum = array_sum(
            array_map(
                static function (int $weight, string $digit): int {
                    return $weight * (int) $digit;
                },
                [6, 5, 7, 2, 3, 4, 5, 6, 7],
                array_slice($chars, 0, 9),
            ),
        );

        $checksum = $sum % 11;

        return $checksum == $chars[9];
    }
}
