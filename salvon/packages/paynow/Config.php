<?php

declare(strict_types=1);

namespace Salvon\Paynow;

use Paynow\Environment;

final class Config
{
    public static function isEnabled(): bool
    {
        return (bool) config('salvon.payments.paynow.enabled', false);
    }

    public static function apiKey(): string
    {
        return (string) config('salvon.payments.paynow.key', '');
    }

    public static function signatureKey(): string
    {
        return (string) config('salvon.payments.paynow.secret', '');
    }

    public static function isSandbox(): bool
    {
        return self::environment() === Environment::SANDBOX;
    }

    public static function environment(): string
    {
        return config('salvon.payments.paynow.env') === 'PRODUCTION'
            ? Environment::PRODUCTION
            : Environment::SANDBOX;
    }

    public static function returnUrl(): string
    {
        return (string) config('salvon.payments.paynow.return_url', '');
    }
}
