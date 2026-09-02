<?php

declare(strict_types=1);

namespace Salvon\Przelewy24;

use Salvon\Przelewy24\DTO\CredentialsData;

final class Config
{
    public static function merchantId(): int
    {
        return (int) config('salvon.payments.przelewy24.merchant_id', 0);
    }

    public static function secretId(): string
    {
        return config('salvon.payments.przelewy24.secret_id', '');
    }

    public static function crc(): string
    {
        return config('salvon.payments.przelewy24.crc', '');
    }

    public static function timeLimit(): int
    {
        return (int) config('salvon.payments.przelewy24.transaction_time_limit', 10);
    }

    public static function waitForTransactionEnd(): bool
    {
        return config('salvon.payments.przelewy24.wait_for_transaction_end', true);
    }

    public static function returnUrl(): string
    {
        return config('salvon.payments.przelewy24.return_url', '');
    }

    public static function validationUrl(): string
    {
        return config('salvon.payments.przelewy24.transaction_validation_url', '');
    }

    public static function advanceValidationUrl(): string
    {
        return config('salvon.payments.przelewy24.advance.transaction_validation_url', '');
    }

    public static function advanceReturnUrl(): string
    {
        return config('salvon.payments.przelewy24.advance.return_url', '');
    }

    public static function isSandbox(): bool
    {
        return config('salvon.payments.przelewy24.sandbox_mode') === true;
    }

    public static function ips(): array
    {
        return config('salvon.payments.przelewy24.ips', [
            '5.252.202.255', '5.252.202.254', '20.215.81.124',
        ]);
    }

    public static function credentials(): CredentialsData
    {
        return new CredentialsData(
            merchantId: self::merchantId(),
            secretId: self::secretId(),
            crc: self::crc(),
        );
    }
}
