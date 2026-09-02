<?php

declare(strict_types=1);

namespace Salvon\UPS;

use Salvon\UPS\DTO\CredentialsData;

final class Config
{
    /** @var (callable(): CredentialsData)|null */
    private static $credentialsResolver = null;

    /**
     * Register a resolver that builds CredentialsData on demand
     * (e.g. from DB-backed admin settings). Called from a service provider.
     *
     * @param callable(): CredentialsData $resolver
     */
    public static function resolveCredentialsUsing(callable $resolver): void
    {
        self::$credentialsResolver = $resolver;
    }

    public static function clientId(): string
    {
        return (string) config('salvon.shipping.ups.client_id', '');
    }

    public static function clientSecret(): string
    {
        return (string) config('salvon.shipping.ups.client_secret', '');
    }

    public static function accountNumber(): string
    {
        return (string) config('salvon.shipping.ups.account_number', '');
    }

    public static function isSandbox(): bool
    {
        return (bool) config('salvon.shipping.ups.sandbox_mode', true);
    }

    public static function tokenCacheTtl(): int
    {
        return (int) config('salvon.shipping.ups.token_cache_ttl', 3500);
    }

    public static function defaultCurrency(): string
    {
        return (string) config('salvon.shipping.ups.default_currency', 'PLN');
    }

    public static function defaultWeightUnit(): string
    {
        return (string) config('salvon.shipping.ups.default_weight_unit', 'KGS');
    }

    public static function defaultDimensionUnit(): string
    {
        return (string) config('salvon.shipping.ups.default_dimension_unit', 'CM');
    }

    public static function credentials(): CredentialsData
    {
        if (self::$credentialsResolver !== null) {
            return (self::$credentialsResolver)();
        }

        return new CredentialsData(
            clientId: self::clientId(),
            clientSecret: self::clientSecret(),
            accountNumber: self::accountNumber(),
            sandbox: self::isSandbox(),
        );
    }
}
