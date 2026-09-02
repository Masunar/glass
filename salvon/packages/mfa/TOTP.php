<?php

declare(strict_types=1);

namespace Salvon\MFA;

use Random\RandomException;
use PragmaRX\Google2FA\Google2FA;

final readonly class TOTP
{
    public static function secret(int $length = 32): string
    {
        return self::instance()->generateSecretKey($length);
    }

    public static function url(string $appName, string $accountName, ?string $secret = null): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s',
            rawurlencode($appName),
            rawurlencode($accountName),
            $secret ?? self::secret(),
        );
    }

    public static function longUrl(string $appName, string $accountName, ?string $secret = null): string
    {
        return self::instance()->getQRCodeUrl(
            $appName,
            $accountName,
            $secret ?? self::secret(),
        );
    }

    public static function verify(string $code, string $secret): bool
    {
        return self::instance()->verify($code, $secret);
    }

    public static function recoveryCode(int $octets = 4, int $octetLength = 8, $separator = '-', array $encryptionAddons = []): ?string
    {
        $codes = self::recoveryCodes($octets, $octetLength, $encryptionAddons);

        if ($codes === null) {
            return null;
        }

        return implode($separator, $codes);
    }

    public static function recoveryCodes(int $codes = 4, int $length = 8, array $encryptionAddons = [], ?string $chars = null): ?array
    {
        try {
            $totalLength = $codes * $length;
            $chars = $chars ?? '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            $rnd = '';

            repeat($totalLength * 2, function () use (&$rnd, $chars) {
                $rnd .= $chars[random_int(0, strlen($chars) - 1)];
            });
            $rnd = str_shuffle($rnd);
            $encryptionAddons = implode('', map($encryptionAddons, static fn(mixed $i) => (string) $i));
            $rnd = str_shuffle($rnd . md5($rnd));
            $rnd = substr($rnd, 0, $totalLength);
            return map(str_split($rnd, $length), function (string $o) use ($length, $encryptionAddons) {
                $o = hash_hmac('sha256', $o, sprintf('RCK:%s:%s:%s', $encryptionAddons, $o, now()->unix()));
                return substr(md5(str_shuffle($o)), 0, $length);
            });
        } catch (RandomException) {
            return null;
        }
    }

    private static function instance(): Google2FA
    {
        return new Google2FA();
    }
}
